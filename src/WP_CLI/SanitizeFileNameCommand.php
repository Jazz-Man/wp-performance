<?php

namespace JazzMan\Performance\WP_CLI;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\Security\SanitizeFileName;
use WP_CLI;
use WP_CLI_Command;

/**
 * Class Sanitize_Command.
 */
class SanitizeFileNameCommand extends WP_CLI_Command implements AutoloadInterface
{
    public function load()
    {
        // TODO: Implement load() method.
    }

    /**
     * Makes all currently uploaded filenames and urls sanitized. Also replaces corresponding files from wp_posts and
     * wp_postmeta.
     *
     * // OPTIONS
     *
     * [--dry-run]
     * : Only prints the changes without replacing.
     *
     * [--verbose]
     * : More output from replacing.
     *
     * [--without-sanitize]
     * : This doesn't make files to lower case and doesn't strip special chars
     *
     * [--network]
     * : More output from replacing.
     *
     * // EXAMPLES
     *
     *     wp sanitize all
     *     wp sanitize all --dry-run
     *
     * @synopsis [--dry-run] [--without-sanitize] [--verbose] [--network]
     *
     * @param mixed $args
     * @param mixed $assoc_args
     *
     * @throws \WP_CLI\ExitException
     */
    public function all($args, $assoc_args)
    {
        $result = self::replace_content($args, $assoc_args);

        if (isset($assoc_args['dry-run'])) {
            WP_CLI::success(
                sprintf(
                    'Found %s from %s attachments to replace.',
                    esc_attr($result['replaced_count']),
                    esc_attr($result['considered_count'])
                )
            );
        } else {
            WP_CLI::success(
                sprintf(
                    'Replaced %s from %s attachments.',
                    esc_attr($result['replaced_count']),
                    esc_attr($result['considered_count'])
                )
            );
        }
    }

    /**
     * Helper: Removes accents from all attachments and posts where those attachments were used.
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @throws \WP_CLI\ExitException
     *
     * @return array
     */
    private static function replace_content($args, $assoc_args)
    {
        if (isset($assoc_args['without-sanitize'])) {
            $assoc_args['sanitize'] = false;
        } else {
            $assoc_args['sanitize'] = true;
        }

        if (isset($assoc_args['network'])) {
            if (is_multisite()) {
                $sites = get_sites();
            } else {
                WP_CLI::error('This is not multisite installation.');

                return 0;
            }
        } else {
            // This way we can use it in network but only to one site
            $sites = ['blog_id' => get_current_blog_id()];
        }

        // Replace mysql later
        global $wpdb;

        // Loop all sites
        foreach ($sites as $site) {
            if (is_multisite()) {
                WP_CLI::line(sprintf('Processing network site: %d', esc_attr($site['blog_id'])));
                switch_to_blog($site['blog_id']);
            }

            // Get all uploads
            $uploads = get_posts(
                [
                    'post_type' => 'attachment',
                    'numberposts' => -1,
                ]
            );

            $all_posts_count = \count($uploads);

            $replaced_count = 0;

            WP_CLI::line(sprintf('Found: %d attachments.', esc_attr(count($uploads))));
            WP_CLI::line('This may take a while...');
            foreach ($uploads as $index => $upload) {
                $ascii_guid = SanitizeFileName::removeAccents($upload->guid, $assoc_args['sanitize']);

                // Replace all files and content if file is different after removing accents
                if ($ascii_guid !== $upload->guid) {
                    ++$replaced_count;

                    /**
                     * Replace all thumbnail sizes of this file from all post contents
                     * Attachment in post content is only rarely file.jpg
                     * More ofter it's like file-800x500.jpg
                     * Only search for the file basename like /wp-content/uploads/2017/01/file without extension.
                     */
                    $file_info = pathinfo($upload->guid);

                    // Check filename without extension so we can replace all thumbnail sizes at once
                    $attachment_string = $file_info['dirname'].'/'.$file_info['filename'];
                    $escaped_attachment_string = SanitizeFileName::removeAccents(
                        $attachment_string,
                        $assoc_args['sanitize']
                    );

                    // We don't need to replace excerpt for example since it doesn't have attachments...

                    WP_CLI::line(
                        sprintf(
                            'REPLACING: %s ---> %s',
                            esc_attr($file_info['basename']),
                            esc_attr($escaped_attachment_string)
                        )
                    );

                    $sql = $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}posts SET post_content = REPLACE (post_content, '%s', '%s') WHERE post_content LIKE '%s';",
                        $attachment_string,
                        $escaped_attachment_string,
                        '%'.$wpdb->esc_like($attachment_string).'%'
                    );

                    if (isset($assoc_args['verbose'])) {
                        self::verboseSql($sql);
                    }

                    if (!isset($assoc_args['dry-run'])) {
                        $wpdb->query($sql);
                    }

                    // DB Replace post meta except _wp_attached_file because it is serialized
                    // This will be done later
                    $sql = $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}postmeta SET meta_value = REPLACE (meta_value, '%s', '%s') WHERE meta_value LIKE '%s' AND meta_key!='_wp_attachment_metadata' AND meta_key!='_wp_attached_file';",
                        $attachment_string,
                        $escaped_attachment_string,
                        '%'.$wpdb->esc_like($attachment_string).'%'
                    );

                    if (isset($assoc_args['verbose'])) {
                        self::verboseSql($sql);
                    }

                    if (!isset($assoc_args['dry-run'])) {
                        $wpdb->query($sql);
                    }

                    // Get full path for file and replace accents for the future filename
                    $full_path = get_attached_file($upload->ID);
                    $ascii_full_path = SanitizeFileName::removeAccents($full_path, $assoc_args['sanitize']);

                    // Move the file
                    WP_CLI::line(
                        sprintf(
                            '----> Checking image:     %s',
                            esc_attr($full_path)
                        )
                    );

                    if (!isset($assoc_args['dry-run'])) {
                        $old_file = SanitizeFileName::renameAccentedFilesInAnyForm($full_path, $ascii_full_path);
                        if ($old_file) {
                            WP_CLI::line(
                                sprintf(
                                    '----> Replaced file:      %s -> %s',
                                    esc_attr(basename($old_file)),
                                    esc_attr(basename($ascii_full_path))
                                )
                            );
                        } else {
                            WP_CLI::line(
                                sprintf(
                                    "----> ERROR: File can't be found: %s",
                                    esc_attr(basename($full_path))
                                )
                            );
                        }
                    }

                    // Replace thumbnails too
                    $file_path = \dirname($full_path);
                    $metadata = wp_get_attachment_metadata($upload->ID);

                    // Correct main file for later usage
                    $ascii_file = SanitizeFileName::removeAccents($metadata['file'], $assoc_args['sanitize']);
                    $metadata['file'] = $ascii_file;

                    // Usually this is image but if this is document instead it won't have different thumbnail sizes
                    if (isset($metadata['sizes'])) {
                        foreach ($metadata['sizes'] as $name => $thumbnail) {
                            $metadata['sizes'][$name]['file'];
                            $thumbnail_path = $file_path.'/'.$thumbnail['file'];

                            $ascii_thumbnail = SanitizeFileName::removeAccents(
                                $thumbnail['file'],
                                $assoc_args['sanitize']
                            );

                            // Update metadata on thumbnail so we can push it back to database
                            $metadata['sizes'][$name]['file'] = $ascii_thumbnail;

                            $ascii_thumbnail_path = $file_path.'/'.$ascii_thumbnail;

                            WP_CLI::line(sprintf('----> Checking thumbnail: %s', esc_attr($thumbnail_path)));

                            if (!isset($assoc_args['dry-run'])) {
                                $old_file = SanitizeFileName::renameAccentedFilesInAnyForm(
                                    $thumbnail_path,
                                    $ascii_thumbnail_path
                                );
                                if ($old_file) {
                                    WP_CLI::line(
                                        sprintf(
                                            '----> Replaced thumbnail: %s -> %s',
                                            esc_attr(basename($old_file)),
                                            esc_attr(basename($ascii_thumbnail_path))
                                        )
                                    );
                                } else {
                                    WP_CLI::line(
                                        sprintf(
                                            "----> ERROR: File can't be found: %s",
                                            esc_attr(basename($thumbnail_path))
                                        )
                                    );
                                }
                            }
                        }
                    }

                    // Serialize fixed metadata so that we can insert it back to database
                    $fixed_metadata = serialize($metadata);

                    // Replace Database
                    if (isset($assoc_args['verbose'])) {
                        WP_CLI::line(sprintf('Replacing attachment %d data in database...', esc_attr($upload->ID)));
                    }

                    if (!isset($assoc_args['dry-run'])) {
                        // Replace guid

                        $wpdb->update($wpdb->posts, ['guid' => $ascii_guid], ['ID' => $upload->ID], ['%s'], ['%d']);

                        // Replace upload name

                        $wpdb->update(
                            $wpdb->postmeta,
                            ['meta_value' => $ascii_file],
                            ['post_id' => '%d', 'meta_key' => '_wp_attached_file'],
                            ['%s'],
                            ['%d']
                        );

                        // Replace meta data like thumbnail fields

                        $wpdb->update(
                            $wpdb->postmeta,
                            ['meta_value' => $fixed_metadata],
                            ['post_id' => '%d', 'meta_key' => '_wp_attachment_metadata'],
                            ['%s'],
                            ['%d']
                        );
                    }

                    // Calculate remaining files
                    $remaining_files = $all_posts_count - $index - 1;

                    // Show some kind of progress to wp-cli user
                    WP_CLI::line();
                    WP_CLI::line(sprintf('Remaining workload: %s attachments...', esc_attr($remaining_files)));
                    WP_CLI::line();
                }
            }
        }

        return ['replaced_count' => $replaced_count, 'considered_count' => $all_posts_count];
    }

    private static function verboseSql(string $sqlString)
    {
        WP_CLI::line(sprintf('RUNNING SQL: %s', esc_attr($sqlString)));
    }

    //END: function
}

WP_CLI::add_command('sanitize', SanitizeFileNameCommand::class);
