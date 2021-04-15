<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\App;
use JazzMan\Performance\Utils\Cache;

/**
 * Class Media.
 */
class Media implements AutoloadInterface
{
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
        add_action( 'pre_get_posts', [$this,'filterQueryAttachmentFilenames'] );

        add_filter(
            'cmb2_valid_img_types',
            static function ($valid_types) {
                $valid_types[] = 'svg';

                return $valid_types;
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
        remove_filter( 'posts_clauses', '_filter_query_attachment_filenames' );
    }

    /**
     * @param  array  $data
     * @param  array  $postarr
     *
     * @return array
     */
    public function setAttachmentTitle(array $data, array $postarr): array
    {
        if (!empty($postarr['file'])) {
            $url = \pathinfo($postarr['file']);
            $extension = !empty($url['extension']) ? ".{$url['extension']}" : false;

            $title = !empty($extension) ? \rtrim($data['post_title'], $extension) : $data['post_title'];

            $data['post_title'] = app_trim_string(app_get_human_friendly($title));
        }

        return $data;
    }

    public function setAttachmentAltTitle(int $post_id)
    {
        $image_title = get_the_title($post_id);

        $image_alt = get_post_meta($post_id, '_wp_attachment_image_alt', true);

        if (empty($image_alt)) {
            update_post_meta($post_id, '_wp_attachment_image_alt', $image_title);
        }
    }

    /**
     * Replace all instances of gravatar with a local image file
     * to remove the call to remote service.
     *
     * @param string            $avatar      image tag for the user's avatar
     * @param int|object|string $id_or_email a user ID, email address, or comment object
     * @param int               $size        square avatar width and height in pixels to retrieve
     * @param string            $default     URL to a default image to use if no avatar is available
     * @param string            $alt         alternative text to use in the avatar image tag
     *
     * @return string `<img>` tag for the user's avatar
     */
    public function replaceGravatar(string $avatar, $id_or_email, int $size, string $default, string $alt): string
    {
        // Bail if disabled.
        if (!App::enabled()) {
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
     * @param string $avatar_list list of default avatars
     *
     * @return string Updated list with images removed
     */
    public function defaultAvatar(string $avatar_list): string
    {
        // Bail if disabled.
        if (!App::enabled()) {
            return $avatar_list;
        }

        // Remove images.
        // Send back the list.
        return \preg_replace('|<img([^>]+)> |i', '', $avatar_list);
    }

    /**
     * @see https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/performance/vip-tweaks.php#L39
     *
     * @param  int  $post_id
     */
    public function setMediaMonthsCache(int $post_id)
    {
        if (App::isImporting()) {
            return;
        }

        // Grab the cache to see if it needs updating
        $media_months = wp_cache_get('wpcom_media_months_array', Cache::CACHE_GROUP);

        if (!empty($media_months)){
            // If the transient exists, and the attachment uploaded doesn't match the first (latest) month or year in the transient, lets clear it.
            $latest_year = get_the_time('Y', $post_id) === $media_months['year'];
            $latest_month = get_the_time('n', $post_id) === $media_months['month'];

            if (!$latest_year || !$latest_month) {
                // the new attachment is not in the same month/year as the data in our cache
                wp_cache_delete('wpcom_media_months_array',Cache::CACHE_GROUP);
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
        $months = wp_cache_get('wpcom_media_months_array',Cache::CACHE_GROUP);

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
limit 1
SQL
            );

            $statement->execute();

            $months = $statement->fetch(\PDO::FETCH_ASSOC);

            wp_cache_set('wpcom_media_months_array', $months,Cache::CACHE_GROUP);
        }

        return $months;
    }

    /**
     * @param array $mimes
     *
     * @return array
     */
    public function allowSvg(array $mimes): array
    {
        $mimes['svg'] = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';

        return $mimes;
    }

    /**
     * @param  array  $data
     * @param  string  $file
     * @param  string  $filename
     *
     * @return array
     */
    public function fixMimeTypeSvg(array $data, string $file, string $filename): array
    {
        $ext = !empty($data['ext']) ? $data['ext'] : '';
        if ('' === $ext) {
            $exploded = \explode('.', $filename);
            $ext = \strtolower(\end($exploded));
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
     * @param array|false        $image
     * @param int          $attachment_id
     * @param array|string $size
     *
     * @return array|bool
     */
    public function fixSvgSizeAttributes($image, int $attachment_id, $size)
    {
        if (is_admin()) {
            return $image;
        }

        [$image_url, $width, $height, $resized] = $image;

        $file_ext = \pathinfo($image_url, PATHINFO_EXTENSION);

        if ('svg' === $file_ext) {
            $width = 60;
            $height = 60;

            if (\is_array($size) && 2 === \count($size)) {
                [$width, $height] = $size;
            }

            return [$image_url, $width, $height, $resized];
        }

        return $image;
    }

    /**
     * @param  array|false  $image
     * @param  int  $attachment_id
     * @param  array|string  $size
     *
     * @return array|false
     */
    public function resizeImageOnTheFly($image, int $attachment_id, $size)
    {
        if (is_admin()) {
            return $image;
        }

        $meta = wp_get_attachment_metadata($attachment_id);

        if (\is_array($size) && !empty($meta) && ($_file = get_attached_file($attachment_id)) && (\file_exists($_file))) {
            $upload = wp_upload_dir();

            $_file_path = \ltrim($_file, $upload['basedir']);

            $image_dirname = \pathinfo($_file_path, PATHINFO_DIRNAME);

            $image_base_url = "{$upload['baseurl']}/{$image_dirname}";

            [$width, $height] = $size;

            if (!empty($meta['sizes'])) {
                foreach ($meta['sizes'] as $key => $value) {
                    if ((int) $value['width'] === (int) $width && (int) $value['height'] === (int) $height) {
                        return $image;
                    }
                }
            }

            // Generate new size
            $resized = image_make_intermediate_size($_file, $width, $height, true);

            if ($resized && !is_wp_error($resized)) {
                $key = \sprintf('resized-%dx%d', $resized['width'], $resized['height']);
                $meta['sizes'][$key] = $resized;
                wp_update_attachment_metadata($attachment_id, $meta);

                $image[0] = "{$image_base_url}/{$resized['file']}";
                $image[1] = $resized['width'];
                $image[2] = $resized['height'];
            }
        }

        return $image;
    }
}
