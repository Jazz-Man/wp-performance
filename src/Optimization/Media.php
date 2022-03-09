<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\Utils\Cache;
use PDO;
use stdClass;
use WP_Comment;

/**
 * Class Media.
 */
class Media implements AutoloadInterface {
    public function load(): void {
        // Disable gravatars

        add_filter('get_avatar', [__CLASS__, 'replaceGravatar'], 1, 5);

        add_filter('default_avatar_select', [__CLASS__, 'defaultAvatar']);
        // Prevent BuddyPress from falling back to Gravatar avatars.
        add_filter('bp_core_fetch_avatar_no_grav', '__return_true');

        add_action('add_attachment', [__CLASS__, 'setMediaMonthsCache']);

        add_action('add_attachment', [__CLASS__, 'setAttachmentAltTitle']);

        add_filter('wp_insert_attachment_data', [__CLASS__, 'setAttachmentTitle'], 10, 2);

        // disable responsive images srcset
        add_filter('wp_calculate_image_srcset_meta', '__return_empty_array', PHP_INT_MAX);

        add_filter('upload_mimes', [__CLASS__, 'allowSvg']);
        add_filter('wp_check_filetype_and_ext', [__CLASS__, 'fixMimeTypeSvg'], 75, 3);
        add_filter('wp_get_attachment_image_src', [__CLASS__, 'fixSvgSizeAttributes'], 10, 3);

        // resize image on the fly
        add_filter('wp_get_attachment_image_src', [__CLASS__, 'resizeImageOnTheFly'], 10, 3);

        add_action('pre_get_posts', function (): void {
            remove_filter('posts_clauses', '_filter_query_attachment_filenames');
        });

        add_filter(
            'cmb2_valid_img_types',
            static function (array $validTypes = []): array {
                $validTypes[] = 'svg';

                return $validTypes;
            }
        );

        if (is_admin()) {
            add_filter('media_library_show_video_playlist', '__return_true');
            add_filter('media_library_show_audio_playlist', '__return_true');
            add_filter('media_library_months_with_files', [__CLASS__, 'mediaLibraryMonthsWithFiles']);
        }
    }

    /**
     * @param array<string,string> $data
     * @param array<string,string> $postarr
     *
     * @return array<string,string>
     */
    public static function setAttachmentTitle(array $data, array $postarr): array {
        if ( ! empty($postarr['file'])) {
            $url = pathinfo($postarr['file']);
            $extension = empty($url['extension']) ? '' : sprintf('.%s', $url['extension']);

            $title = rtrim($data['post_title'], $extension);

            $data['post_title'] = app_trim_string(app_get_human_friendly($title));
        }

        return $data;
    }

    public static function setAttachmentAltTitle(int $postId): void {

        /** @var string|null $imageAlt */
        $imageAlt = get_post_meta($postId, '_wp_attachment_image_alt', true);

        if (empty($imageAlt)) {
            update_post_meta($postId, '_wp_attachment_image_alt', get_the_title($postId));
        }
    }

    /**
     * Replace all instances of gravatar with a local image file
     * to remove the call to remote service.
     *
     * @param string                $avatar    image tag for the user's avatar
     * @param int|string|WP_Comment $idOrEmail a user ID, email address, or comment object
     * @param int                   $size      square avatar width and height in pixels to retrieve
     * @param string                $default   URL to a default image to use if no avatar is available
     * @param string                $alt       alternative text to use in the avatar image tag
     *
     * @return string `<img>` tag for the user's avatar
     *
     * @psalm-suppress MixedArgument
     */
    public static function replaceGravatar(string $avatar, $idOrEmail, int $size, string $default, string $alt): string {
        // Bail if disabled.
        if ( ! app_is_enabled_wp_performance()) {
            return $avatar;
        }

        // Return the avatar.
        return sprintf(
            '<img alt="%1$s" src="%2$s" class="avatar avatar-%3$s photo" height="%3$s" width="%3$s" style="background:#eee;" />',
            esc_attr($alt),
            'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==',
            esc_attr((string) $size)
        );
    }

    /**
     * Remove avatar images from the default avatar list.
     *
     * @param string $avatarList list of default avatars
     *
     * @return string Updated list with images removed
     */
    public static function defaultAvatar(string $avatarList): string {
        // Bail if disabled.
        if ( ! app_is_enabled_wp_performance()) {
            return $avatarList;
        }

        // Remove images.
        // Send back the list.
        return (string) preg_replace('#<img([^>]+)> #i', '', $avatarList);
    }

    /**
     * @see https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/performance/vip-tweaks.php#L39
     */
    public function setMediaMonthsCache(int $postId): void {
        if (app_is_wp_importing()) {
            return;
        }

        // Grab the cache to see if it needs updating
        /** @var stdClass[]|false $mediaMonths */
        $mediaMonths = wp_cache_get('wpcom_media_months_array', Cache::CACHE_GROUP);

        if ( ! empty($mediaMonths)) {
            $cachedLatestYear = empty($mediaMonths[0]->year) ? '' : (string) $mediaMonths[0]->year;
            $cachedLatestMonth = empty($mediaMonths[0]->month) ? '' : (string) $mediaMonths[0]->month;

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
     * @param mixed|null $months
     *
     * @return stdClass[]
     *
     * @see https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/performance/vip-tweaks.php#L65
     */
    public static function mediaLibraryMonthsWithFiles($months = null): array {
        global $wpdb;

        /** @var stdClass[]|false $months */
        $months = wp_cache_get('wpcom_media_months_array', Cache::CACHE_GROUP);

        if (false === $months) {
            $pdo = app_db_pdo();

            $pdoStatement = $pdo->prepare(
                <<<SQL
                    select 
                      distinct year( post_date ) as year, 
                      month( post_date ) as month
                    from $wpdb->posts
                    where post_type = 'attachment'
                    order by post_date desc 
SQL
            );

            $pdoStatement->execute();

            $months = $pdoStatement->fetchAll(PDO::FETCH_OBJ);

            wp_cache_set('wpcom_media_months_array', $months, Cache::CACHE_GROUP);
        }

        /* @var stdClass[] $months  */
        return $months;
    }

    /**
     * @param array<string,string> $mimes
     *
     * @return array<string,string>
     */
    public static function allowSvg(array $mimes): array {
        $mimes['svg'] = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';

        return $mimes;
    }

    /**
     * @param array<string,string> $data
     * @param string               $file
     * @param string               $filename
     *
     * @return array<string, string>
     */
    public static function fixMimeTypeSvg(array $data, string $file, string $filename): array {
        $ext = empty($data['ext']) ? '' : $data['ext'];

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
     * @param array<array-key,string|int|bool>|false $image
     * @param string|int[]                           $size
     *
     * @return array<array-key,string|int|bool>|false
     */
    public static function fixSvgSizeAttributes($image, int $attachmentId, $size) {
        if (is_admin()) {
            return $image;
        }

        if (empty($image)) {
            return $image;
        }

        $fileExt = pathinfo((string) $image[0], PATHINFO_EXTENSION);

        if ('svg' === $fileExt) {
            $image[1] = 60;
            $image[2] = 60;

            if (is_array($size) && 2 === count($size)) {
                $image[1] = $size[0];
                $image[2] = $size[1];
            }
        }

        return $image;
    }

    /**
     * @param array<string,mixed>|false $image
     * @param int[]|string              $size
     *
     * @return array<string, mixed>|bool|array<int|string, mixed>
     *
     * @psalm-return array<int|string, mixed>|false
     */
    public static function resizeImageOnTheFly($image, int $attachmentId, $size) {
        if (is_admin()) {
            return $image;
        }

        if (! is_array($size)) {
            return $image;
        }
        $meta = wp_get_attachment_metadata($attachmentId);

        if (empty($meta)) {
            return $image;
        }

        $upload = wp_upload_dir();
        $filePath = sprintf('%s/%s', $upload['basedir'], $meta['file']);

        if ( ! file_exists($filePath)) {
            return $image;
        }

        $imageDirname = pathinfo($filePath, PATHINFO_DIRNAME);

        $imageBaseUrl = sprintf('%s/%s', $upload['baseurl'], $imageDirname);

        list($width, $height) = $size;

        if ( ! empty($meta['sizes']) && self::isImageSizesExist($meta['sizes'], $width, $height )) {
            return $image;
        }

        // Generate new size
        /** @var array{path:string, file:string, width:int, height:int, 'mime-type':string}|false $resized */
        $resized = image_make_intermediate_size($filePath, $width, $height, true);

        if (!empty($resized)) {
            $metaSizeKey = sprintf('resized-%dx%d', $resized['width'], $resized['height']);
            $meta['sizes'][$metaSizeKey] = $resized;
            wp_update_attachment_metadata($attachmentId, $meta);

            /** @var array{0:string,1:int,2:int,3:bool} $image */
            $image = (array) $image;

            $image[0] = sprintf('%s/%s', $imageBaseUrl, $resized['file']);
            $image[1] = $resized['width'];
            $image[2] = $resized['height'];
        }

        return $image;
    }

    /**
     * @param array<array-key,array<string,int|string>> $sizes
     * @param int                                       $width
     * @param int                                       $height
     *
     * @return bool
     */
    private static function isImageSizesExist(array $sizes, int $width, int $height): bool {
        foreach ($sizes as $size) {
            if (!empty($size['width']) && (int) $size['width'] !== $width) {
                continue;
            }

            if (!empty($size['height']) && (int) $size['height'] !== $height) {
                continue;
            }

            return true;
        }

        return false;
    }
}
