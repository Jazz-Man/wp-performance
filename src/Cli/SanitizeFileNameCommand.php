<?php

namespace JazzMan\Performance\Cli;

use JazzMan\Performance\Security\SanitizeFileName;
use WP_CLI;

/**
 * Class Sanitize_Command.
 */
class SanitizeFileNameCommand extends Command
{

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
     * : More output from replacing.
     *
     * // EXAMPLES
     *
     *     wp sanitize all
     *     wp sanitize all --dry-run
     *
     * @synopsis [--dry-run] [--without-sanitize] [--verbose]
     *
     * @param  mixed  $args
     * @param  mixed  $assocArgs
     *
     */
    public function all($args, $assocArgs)
    {
        $result = self::replaceContent($args, $assocArgs);

        if (isset($assocArgs['dry-run'])) {
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
     * @param  array  $args
     * @param  array  $assocArgs
     *
     * @return array
     */
    private function replaceContent($args, $assocArgs)
    {
        if (isset($assocArgs['without-sanitize'])) {
            $assocArgs['sanitize'] = false;
        } else {
            $assocArgs['sanitize'] = true;
        }

        $sites = $this->getAllSites();

        $replacedCount = 0;
        $allPostsCount = 0;

        // Replace mysql later
        global $wpdb;

        // Loop all sites
        foreach ($sites as $siteId) {
            $this->maybeSwitchToBlog($siteId);

            // Get all uploads
            $uploads = get_posts(
                [
                    'post_type' => 'attachment',
                    'numberposts' => -1,
                ]
            );

            $allPostsCount = \count($uploads);

            $replacedCount = 0;

            WP_CLI::line(sprintf('Found: %d attachments.', esc_attr(count($uploads))));
            WP_CLI::line('This may take a while...');
            foreach ($uploads as $index => $upload) {
                $asciiGuid = SanitizeFileName::removeAccents($upload->guid, $assocArgs['sanitize']);

                // Replace all files and content if file is different after removing accents
                if ($asciiGuid !== $upload->guid) {
                    ++$replacedCount;

                    /**
                     * Replace all thumbnail sizes of this file from all post contents
                     * Attachment in post content is only rarely file.jpg
                     * More ofter it's like file-800x500.jpg
                     * Only search for the file basename like /wp-content/uploads/2017/01/file without extension.
                     */
                    $fileInfo = pathinfo($upload->guid);

                    // Check filename without extension so we can replace all thumbnail sizes at once
                    $attachmentString = $fileInfo['dirname'].'/'.$fileInfo['filename'];
                    $escapedAttachmentString = SanitizeFileName::removeAccents(
                        $attachmentString,
                        $assocArgs['sanitize']
                    );

                    // We don't need to replace excerpt for example since it doesn't have attachments...

                    WP_CLI::line(
                        sprintf(
                            'REPLACING: %s ---> %s',
                            esc_attr($fileInfo['basename']),
                            esc_attr($escapedAttachmentString)
                        )
                    );

                    $sql = $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}posts SET post_content = REPLACE (post_content, '%s', '%s') WHERE post_content LIKE '%s';",
                        $attachmentString,
                        $escapedAttachmentString,
                        '%'.$wpdb->esc_like($attachmentString).'%'
                    );

                    if (isset($assocArgs['verbose'])) {
                        self::verboseSql($sql);
                    }

                    if (!isset($assocArgs['dry-run'])) {
                        $wpdb->query($sql);
                    }

                    // DB Replace post meta except _wp_attached_file because it is serialized
                    // This will be done later
                    $sql = $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}postmeta SET meta_value = REPLACE (meta_value, '%s', '%s') WHERE meta_value LIKE '%s' AND meta_key!='_wp_attachment_metadata' AND meta_key!='_wp_attached_file';",
                        $attachmentString,
                        $escapedAttachmentString,
                        '%'.$wpdb->esc_like($attachmentString).'%'
                    );

                    if (isset($assocArgs['verbose'])) {
                        self::verboseSql($sql);
                    }

                    if (!isset($assocArgs['dry-run'])) {
                        $wpdb->query($sql);
                    }

                    // Get full path for file and replace accents for the future filename
                    $fullPath = get_attached_file($upload->ID);
                    $asciiFullPath = SanitizeFileName::removeAccents($fullPath, $assocArgs['sanitize']);

                    // Move the file
                    WP_CLI::line(
                        sprintf(
                            '----> Checking image:     %s',
                            esc_attr($fullPath)
                        )
                    );

                    if (!isset($assocArgs['dry-run'])) {
                        $oldFile = SanitizeFileName::renameAccentedFilesInAnyForm($fullPath, $asciiFullPath);
                        if ($oldFile) {
                            WP_CLI::line(
                                sprintf(
                                    '----> Replaced file:      %s -> %s',
                                    esc_attr(basename($oldFile)),
                                    esc_attr(basename($asciiFullPath))
                                )
                            );
                        } else {
                            WP_CLI::line(
                                sprintf(
                                    "----> ERROR: File can't be found: %s",
                                    esc_attr(basename($fullPath))
                                )
                            );
                        }
                    }

                    // Replace thumbnails too
                    $filePath = \dirname($fullPath);
                    $metadata = wp_get_attachment_metadata($upload->ID);

                    // Correct main file for later usage
                    $asciiFile = SanitizeFileName::removeAccents($metadata['file'], $assocArgs['sanitize']);
                    $metadata['file'] = $asciiFile;

                    // Usually this is image but if this is document instead it won't have different thumbnail sizes
                    if (isset($metadata['sizes'])) {
                        foreach ($metadata['sizes'] as $name => $thumbnail) {
                            $metadata['sizes'][$name]['file'];
                            $thumbnailPath = $filePath.'/'.$thumbnail['file'];

                            $asciiThumbnail = SanitizeFileName::removeAccents(
                                $thumbnail['file'],
                                $assocArgs['sanitize']
                            );

                            // Update metadata on thumbnail so we can push it back to database
                            $metadata['sizes'][$name]['file'] = $asciiThumbnail;

                            $asciiThumbnailPath = $filePath.'/'.$asciiThumbnail;

                            WP_CLI::line(sprintf('----> Checking thumbnail: %s', esc_attr($thumbnailPath)));

                            if (!isset($assocArgs['dry-run'])) {
                                $oldFile = SanitizeFileName::renameAccentedFilesInAnyForm(
                                    $thumbnailPath,
                                    $asciiThumbnailPath
                                );
                                if ($oldFile) {
                                    WP_CLI::line(
                                        sprintf(
                                            '----> Replaced thumbnail: %s -> %s',
                                            esc_attr(basename($oldFile)),
                                            esc_attr(basename($asciiThumbnailPath))
                                        )
                                    );
                                } else {
                                    WP_CLI::line(
                                        sprintf(
                                            "----> ERROR: File can't be found: %s",
                                            esc_attr(basename($thumbnailPath))
                                        )
                                    );
                                }
                            }
                        }
                    }

                    // Serialize fixed metadata so that we can insert it back to database
                    $fixedMetadata = serialize($metadata);

                    // Replace Database
                    if (isset($assocArgs['verbose'])) {
                        WP_CLI::line(sprintf('Replacing attachment %d data in database...', esc_attr($upload->ID)));
                    }

                    if (!isset($assocArgs['dry-run'])) {
                        // Replace guid

                        $wpdb->update($wpdb->posts, ['guid' => $asciiGuid], ['ID' => $upload->ID], ['%s'], ['%d']);

                        // Replace upload name

                        $wpdb->update(
                            $wpdb->postmeta,
                            ['meta_value' => $asciiFile],
                            ['post_id' => '%d', 'meta_key' => '_wp_attached_file'],
                            ['%s'],
                            ['%d']
                        );

                        // Replace meta data like thumbnail fields

                        $wpdb->update(
                            $wpdb->postmeta,
                            ['meta_value' => $fixedMetadata],
                            ['post_id' => '%d', 'meta_key' => '_wp_attachment_metadata'],
                            ['%s'],
                            ['%d']
                        );
                    }

                    // Calculate remaining files
                    $remainingFiles = $allPostsCount - $index - 1;

                    // Show some kind of progress to wp-cli user
                    WP_CLI::line();
                    WP_CLI::line(sprintf('Remaining workload: %s attachments...', esc_attr($remainingFiles)));
                    WP_CLI::line();
                }
            }
        }

        return ['replaced_count' => $replacedCount, 'considered_count' => $allPostsCount];
    }

    private static function verboseSql(string $sqlString)
    {
        WP_CLI::line(sprintf('RUNNING SQL: %s', esc_attr($sqlString)));
    }

    //END: function
}

WP_CLI::add_command('sanitize', SanitizeFileNameCommand::class);
