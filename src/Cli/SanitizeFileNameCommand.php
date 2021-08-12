<?php

namespace JazzMan\Performance\Cli;

use Exception;
use JazzMan\Performance\Security\SanitizeFileName;
use WP_CLI;
use WP_Post;

/**
 * Class Sanitize_Command.
 */
class SanitizeFileNameCommand extends Command
{
    /**
     * @var bool
     */
    private bool $isDryRun = false;
    /**
     * @var bool
     */
    private bool $isSanitize = false;
    /**
     * @var bool
     */
    private bool $isVerbose = false;
    /**
     * @var \wpdb
     */
    private \wpdb $wpdb;

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
     * @param mixed $args
     * @param array<string,bool> $assocArgs
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @return void
     */
    public function all(?array $args = null, array $assocArgs = []): void
    {
        // Replace mysql later
        global $wpdb;

        $this->wpdb = $wpdb;

        $this->isDryRun = ! empty($assocArgs['dry-run']) && $assocArgs['dry-run'];
        $this->isSanitize = ! empty($assocArgs['without-sanitize']) && $assocArgs['without-sanitize'];
        $this->isVerbose = ! empty($assocArgs['verbose']) && $assocArgs['verbose'];

        $result = self::replaceContent();

        $message = $this->isDryRun ?
            sprintf(
                'Found %d from %d attachments to replace.',
                esc_attr($result['replaced_count']),
                esc_attr($result['considered_count'])
            ) :
            sprintf(
                'Replaced %d from %d attachments.',
                esc_attr($result['replaced_count']),
                esc_attr($result['considered_count'])
            );

        WP_CLI::success($message);
    }

    /**
     * Helper: Removes accents from all attachments and posts where those attachments were used.
     */
    private function replaceContent(): array
    {
        $sites = $this->getAllSites();

        $replacedCount = 0;
        $allPostsCount = 0;

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

            $allPostsCount = count($uploads);

            $replacedCount = 0;

            WP_CLI::line(sprintf('Found: %d attachments.', esc_attr($allPostsCount)));
            WP_CLI::line('This may take a while...');
            foreach ($uploads as $index => $upload) {
                $asciiGuid = SanitizeFileName::removeAccents($upload->guid, $this->isSanitize);

                // Replace all files and content if file is different after removing accents
                if ($asciiGuid !== $upload->guid) {
                    ++$replacedCount;

                    // Get full path for file and replace accents for the future filename
                    $fullPath = get_attached_file($upload->ID);

                    $this->replacePostContent($upload);
                    $this->renameImageFile($fullPath);

                    // Replace thumbnails too
                    $fileDirName = dirname($fullPath);
                    $metadata = wp_get_attachment_metadata($upload->ID);

                    // Correct main file for later usage
                    $asciiFile = SanitizeFileName::removeAccents($metadata['file'], $this->isSanitize);
                    $metadata['file'] = $asciiFile;

                    // Usually this is image but if this is document instead it won't have different thumbnail sizes
                    if (isset($metadata['sizes'])) {
                        foreach ($metadata['sizes'] as $name => $sizeData) {
                            $this->renameImageSizes($name, $sizeData, $fileDirName, $metadata);
                        }
                    }

                    // Serialize fixed metadata so that we can insert it back to database
                    $fixedMetadata = maybe_serialize($metadata);

                    // Replace Database
                    if ($this->isVerbose) {
                        WP_CLI::line(sprintf('Replacing attachment %d data in database...', esc_attr($upload->ID)));
                    }

                    if ( ! $this->isDryRun) {
                        // Replace guid

                        $this->wpdb->update(
                            $this->wpdb->posts,
                            ['guid' => $asciiGuid],
                            ['ID' => $upload->ID],
                            ['%s'],
                            ['%d']
                        );

                        // Replace upload name

                        $this->wpdb->update(
                            $this->wpdb->postmeta,
                            ['meta_value' => $asciiFile],
                            ['post_id' => '%d', 'meta_key' => '_wp_attached_file'],
                            ['%s'],
                            ['%d']
                        );

                        // Replace meta data like thumbnail fields

                        $this->wpdb->update(
                            $this->wpdb->postmeta,
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

    /**
     * @noinspection SqlResolve 
     *
     * @return void
     */
    private function replacePostContent(WP_Post $attachment): void
    {
        /**
         * Replace all thumbnail sizes of this file from all post contents
         * Attachment in post content is only rarely file.jpg
         * More ofter it's like file-800x500.jpg
         * Only search for the file basename like /wp-content/uploads/2017/01/file without extension.
         */
        $fileInfo = pathinfo($attachment->guid);

        // Check filename without extension so we can replace all thumbnail sizes at once
        $attachmentFileName = $fileInfo['dirname'].'/'.$fileInfo['filename'];

        $escapedName = SanitizeFileName::removeAccents($attachmentFileName, $this->isSanitize);

        WP_CLI::line(
            sprintf(
                'REPLACING: %s ---> %s',
                esc_attr($fileInfo['basename']),
                esc_attr($escapedName)
            )
        );

        /** @var string $postContentSql */
        $postContentSql = $this->wpdb->prepare(
            "UPDATE {$this->wpdb->posts} SET post_content = REPLACE (post_content, '%s', '%s') WHERE post_content LIKE '%s';",
            $attachmentFileName,
            $escapedName,
            '%'.$this->wpdb->esc_like($attachmentFileName).'%'
        );

        // DB Replace post meta except _wp_attached_file because it is serialized
        // This will be done later
        /** @var string $metaValueSql */
        $metaValueSql = $this->wpdb->prepare(
            "UPDATE {$this->wpdb->postmeta} SET meta_value = REPLACE (meta_value, '%s', '%s') WHERE meta_value LIKE '%s' AND meta_key != '_wp_attachment_metadata' AND meta_key != '_wp_attached_file';",
            $attachmentFileName,
            $escapedName,
            '%'.$this->wpdb->esc_like($attachmentFileName).'%'
        );

        if ($this->isVerbose) {
            self::verboseSql($postContentSql, "Post Content ($attachment->ID)");
            self::verboseSql($metaValueSql, "Meta Value ($attachment->ID)");
        }

        if ( ! $this->isDryRun) {
            $this->wpdb->query($postContentSql);
            $this->wpdb->query($metaValueSql);
        }
    }

    private function renameImageFile(string $fullPath): void
    {
        $asciiFullPath = SanitizeFileName::removeAccents($fullPath, $this->isSanitize);

        // Move the file
        WP_CLI::line(sprintf('----> Checking image:     %s', esc_attr($fullPath)));

        if ( ! $this->isDryRun) {
            $oldFile = SanitizeFileName::renameAccentedFilesInAnyForm($fullPath, $asciiFullPath);

            $message = $oldFile ?
                sprintf(
                    '----> Replaced file:      %s -> %s',
                    esc_attr(basename($oldFile)),
                    esc_attr(basename($asciiFullPath))
                ) :
                sprintf(
                    "----> ERROR: File can't be found: %s",
                    esc_attr(basename($fullPath))
                );

            WP_CLI::line($message);
            unset($message);
        }
    }

    private function renameImageSizes(string $sizeName, array $thumbnail, string $fileDirName, array &$metadata): void
    {
        $thumbnailPath = $fileDirName.'/'.$thumbnail['file'];

        $asciiThumbnail = SanitizeFileName::removeAccents(
            $thumbnail['file'],
            $this->isSanitize
        );

        // Update metadata on thumbnail so we can push it back to database
        $metadata['sizes'][$sizeName]['file'] = $asciiThumbnail;

        $asciiThumbnailPath = $fileDirName.'/'.$asciiThumbnail;

        WP_CLI::line(sprintf('----> Checking thumbnail: %s', esc_attr($thumbnailPath)));

        if ( ! $this->isDryRun) {
            $oldFile = SanitizeFileName::renameAccentedFilesInAnyForm(
                $thumbnailPath,
                $asciiThumbnailPath
            );

            $message = $oldFile ?
                sprintf(
                    '----> Replaced thumbnail: %s -> %s',
                    esc_attr(basename($oldFile)),
                    esc_attr(basename($asciiThumbnailPath))
                ) :
                sprintf(
                    "----> ERROR: File can't be found: %s",
                    esc_attr(basename($thumbnailPath))
                );

            WP_CLI::line($message);
        }
    }

    private static function verboseSql(string $sqlString, string $label): void
    {
        WP_CLI::line(
            sprintf(
                'RUNNING "%s" SQL: %s',
                esc_attr($label),
                esc_attr($sqlString)
            )
        );
    }

    //END: function
}

try {
    WP_CLI::add_command('sanitize', SanitizeFileNameCommand::class);
} catch (Exception $e) {
    app_error_log($e, __FILE__);
}
