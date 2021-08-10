<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\App;
use JazzMan\Performance\Utils\Cache;
use PDO;

/**
 * Class Media.
 */
class Media implements AutoloadInterface
{
    /**
     * @return void
     */
    public function load()
    {
        // Disable gravatars
        add_filter('get_avatar', [$this, 'replaceGravatar'], 1, 5);
        add_filter('default_avatar_select', [$this, 'defaultAvatar']);
        // Prevent BuddyPress from falling back to Gravatar avatars.
        add_filter('bp_core_fetch_avatar_no_grav', '__return_true');

        add_action('add_attachment', [$this, 'setMediaMonthsCache']);
        add_action('add_attachment', [$this, 'setAttachmentAltTitle']);
        add_filter('wp_insert_attachment_data', [$this, 'setAttachmentTitle'], 10, 2);

        // disable responsive images srcset
        add_filter('wp_calculate_image_srcset_meta', '__return_empty_array', PHP_INT_MAX);

        add_filter('upload_mimes', [$this, 'allowSvg']);
        add_filter('wp_check_filetype_and_ext', [$this, 'fixMimeTypeSvg'], 75, 3);
        add_filter('wp_get_attachment_image_src', [$this, 'fixSvgSizeAttributes'], 10, 3);
        // resize image on the fly
        add_filter('wp_get_attachment_image_src', [$this, 'resizeImageOnTheFly'], 10, 3);
        add_action('pre_get_posts', [$this, 'filterQueryAttachmentFilenames']);

        add_filter(
            'cmb2_valid_img_types',
            static function (array $validTypes = []) {
                $validTypes[] = 'svg';

                return $validTypes;
            }
        );

        if (is_admin()) {
            add_filter('media_library_show_video_playlist', '__return_true');
            add_filter('media_library_show_audio_playlist', '__return_true');
            add_filter('media_library_months_with_files', [$this, 'mediaLibraryMonthsWithFiles']);
        }
    }

    public function filterQueryAttachmentFilenames(): void
    {
        remove_filter('posts_clauses', '_filter_query_attachment_filenames');
    }

    public function setAttachmentTitle(array $data, array $postarr): array
    {
        if ( ! empty($postarr['file'])) {
            $url = pathinfo($postarr['file']);
            $extension = ! empty($url['extension']) ? ".{$url['extension']}" : false;

            $title = ! empty($extension) ? rtrim($data['post_title'], $extension) : $data['post_title'];

            $data['post_title'] = app_trim_string(app_get_human_friendly($title));
        }

        return $data;
    }

    public function setAttachmentAltTitle(int $postId): void
    {
        $imageAlt = get_post_meta($postId, '_wp_attachment_image_alt', true);

        if (empty($imageAlt)) {
            update_post_meta($postId, '_wp_attachment_image_alt', get_the_title($postId));
        }
    }

    /**
     * Replace all instances of gravatar with a local image file
     * to remove the call to remote service.
     *
     * @param string            $avatar    image tag for the user's avatar
     * @param int|object|string $idOrEmail a user ID, email address, or comment object
     * @param int               $size      square avatar width and height in pixels to retrieve
     * @param string            $default   URL to a default image to use if no avatar is available
     * @param string            $alt       alternative text to use in the avatar image tag
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @return string `<img>` tag for the user's avatar
     */
    public function replaceGravatar(string $avatar, $idOrEmail, int $size, string $default, string $alt): string
    {
        // Bail if disabled.
        if ( ! app_is_enabled_wp_performance()) {
            return $avatar;
        }

        // Return the avatar.
        return sprintf(
            '<img alt="%1$s" src="%2$s" class="avatar avatar-%3$s photo" height="%3$s" width="%3$s" style="background:#eee;" />',
            esc_attr($alt),
            'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==',
            esc_attr($size)
        );
    }

    /**
     * Remove avatar images from the default avatar list.
     *
     * @param string $avatarList list of default avatars
     *
     * @return string Updated list with images removed
     */
    public function defaultAvatar(string $avatarList): string
    {
        // Bail if disabled.
        if ( ! app_is_enabled_wp_performance()) {
            return $avatarList;
        }

        // Remove images.
        // Send back the list.
        return preg_replace('|<img([^>]+)> |i', '', $avatarList);
    }

    /**
     * @see https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/performance/vip-tweaks.php#L39
     *
     * @return void
     */
    public function setMediaMonthsCache(int $postId)
    {
        if (app_is_wp_importing()) {
            return;
        }

        // Grab the cache to see if it needs updating
        $mediaMonths = wp_cache_get('wpcom_media_months_array', Cache::CACHE_GROUP);

        if ( ! empty($mediaMonths)) {
            $cachedLatestYear = ! empty($mediaMonths[0]->year) ? $mediaMonths[0]->year : '';
            $cachedLatestMonth = ! empty($mediaMonths[0]->month) ? $mediaMonths[0]->month : '';

            // If the transient exists, and the attachment uploaded doesn't match the first (latest) month or year in the transient, lets clear it.
            $latestYear = get_the_time('Y', $postId) === $cachedLatestYear;
            $latestMonth = get_the_time('n', $postId) === $cachedLatestMonth;

            if ( ! $latestYear || ! $latestMonth) {
                // the new attachment is not in the same month/year as the data in our cache
                wp_cache_delete('wpcom_media_months_array', Cache::CACHE_GROUP);
            }
        }
    }

    /**
     * @see https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/performance/vip-tweaks.php#L65
     *
     * @return null|array|mixed|object
     */
    public function mediaLibraryMonthsWithFiles()
    {
        $months = wp_cache_get('wpcom_media_months_array', Cache::CACHE_GROUP);

        if (false === $months) {
            global $wpdb;
            $pdo = app_db_pdo();

            $statement = $pdo->prepare(
                <<<SQL
                    select 
                      distinct year( post_date ) as year, 
                      month( post_date ) as month
                    from $wpdb->posts
                    where post_type = 'attachment'
                    order by post_date desc 
SQL
            );

            $statement->execute();

            $months = $statement->fetchAll(PDO::FETCH_OBJ);

            wp_cache_set('wpcom_media_months_array', $months, Cache::CACHE_GROUP);
        }

        return $months;
    }

    public function allowSvg(array $mimes): array
    {
        $mimes['svg'] = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';

        return $mimes;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function fixMimeTypeSvg(array $data, string $file, string $filename): array
    {
        $ext = ! empty($data['ext']) ? $data['ext'] : '';
        if ('' === $ext) {
            $exploded = explode('.', $filename);
            $ext = strtolower(end($exploded));
        }

        if ('svg' === $ext) {
            $data['type'] = 'image/svg+xml';
            $data['ext'] = 'svg';
        } elseif ('svgz' === $ext) {
            $data['type'] = 'image/svg+xml';
            $data['ext'] = 'svgz';
        }

        return $data;
    }

    /**
     * @param array<string,mixed>  $image
     * @param array|string $size
     *
     * @SuppressWarnings (PHPMD.UnusedFormalParameter)
     * @SuppressWarnings (PHPMD.UnusedLocalVariable)
     *
     * @return array
     *
     * @psalm-return array<int|string, mixed>
     */
    public function fixSvgSizeAttributes($image, int $attachmentId, $size): array
    {
        if (is_admin()) {
            return $image;
        }

        [$imageUrl, $width, $height, $resized] = $image;

        $fileExt = pathinfo($imageUrl, PATHINFO_EXTENSION);

        if ('svg' === $fileExt) {
            $width = 60;
            $height = 60;

            if (is_array($size) && 2 === count($size)) {
                [$width, $height] = $size;
            }

            return [$imageUrl, $width, $height, $resized];
        }

        return $image;
    }

    /**
     * @param array|false  $image
     * @param array|string $size
     *
     * @return array|false
     */
    public function resizeImageOnTheFly($image, int $attachmentId, $size)
    {
        if (is_admin() || ! is_array($size)) {
            return $image;
        }

        $meta = wp_get_attachment_metadata($attachmentId);

        if (empty($meta)) {
            return $image;
        }

        $upload = wp_upload_dir();
        $filePath = "{$upload['basedir']}/{$meta['file']}";

        if ( ! file_exists($filePath)) {
            return $image;
        }

        $imageDirname = pathinfo($filePath, PATHINFO_DIRNAME);

        $imageBaseUrl = "{$upload['baseurl']}/$imageDirname";

        [$width, $height] = $size;

        if ( ! empty($meta['sizes']) && $this->isImageSizesExist($meta, (int) $width, (int) $height)) {
            return $image;
        }

        // Generate new size
        $resized = image_make_intermediate_size($filePath, $width, $height, true);

        if (is_wp_error($resized)) {
            return $image;
        }

        if ($resized) {
            $metaSizeKey = sprintf('resized-%dx%d', $resized['width'], $resized['height']);
            $meta['sizes'][$metaSizeKey] = $resized;
            wp_update_attachment_metadata($attachmentId, $meta);

            $image[0] = "$imageBaseUrl/{$resized['file']}";
            $image[1] = $resized['width'];
            $image[2] = $resized['height'];
        }

        return $image;
    }

    /**
     * @param  array<string,array<string,mixed>>  $imageMeta
     * @param  int  $width
     * @param  int  $height
     *
     * @return bool
     */
    private function isImageSizesExist(array $imageMeta, int $width, int $height): bool
    {
        foreach ($imageMeta['sizes'] as $key => $value) {
            if ((int) $value['width'] === $width && (int) $value['height'] === $height) {
                return true;
            }
        }

        return false;
    }
}
