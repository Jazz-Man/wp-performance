<?php

namespace JazzMan\Performance;

use JazzMan\AutoloadInterface\AutoloadInterface;

/**
 * Class Media.
 */
class Media implements AutoloadInterface
{
    public function load()
    {
        // Disable gravatars
        add_filter('get_avatar', [$this, 'replace_gravatar'], 1, 5);
        add_filter('default_avatar_select', [$this, 'default_avatar']);
        // Prevent BuddyPress from falling back to Gravatar avatars.
        add_filter('bp_core_fetch_avatar_no_grav', '__return_true');

        add_action('add_attachment', [$this, 'bust_media_months_cache']);

        add_filter('upload_mimes', [$this, 'allow_svg']);
        add_filter('wp_check_filetype_and_ext', [$this, 'fix_mime_type_svg'], 75, 4);

        // resize image on the fly
        add_filter('wp_get_attachment_image_src', [$this, 'resize_image_on_the_fly'], 10, 3);

        if (is_admin()) {
            add_filter('media_library_show_video_playlist', '__return_true');
            add_filter('media_library_show_audio_playlist', '__return_true');
            add_filter('media_library_months_with_files', [$this, 'media_library_months_with_files']);
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
    public function replace_gravatar($avatar, $id_or_email, $size, $default, $alt)
    {
        // Bail if disabled.
        if (!App::enabled()) {
            return $avatar;
        }

        // Swap out the file for a base64 encoded image.
        $image = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
        $avatar = "<img alt='{$alt}' src='{$image}' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' style='background:#eee;' />";

        // Return the avatar.
        return $avatar;
    }

    /**
     * Remove avatar images from the default avatar list.
     *
     * @param string $avatar_list list of default avatars
     *
     * @return string Updated list with images removed
     */
    public function default_avatar($avatar_list)
    {
        // Bail if disabled.
        if (!App::enabled()) {
            return $avatar_list;
        }

        // Remove images.
        $avatar_list = preg_replace('|<img([^>]+)> |i', '', $avatar_list);

        // Send back the list.
        return $avatar_list;
    }

    /**
     * @see https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/performance/vip-tweaks.php#L39
     *
     * @param int $post_id
     */
    public function bust_media_months_cache($post_id)
    {
        if (App::isImporting()) {
            return;
        }

        // Grab the transient to see if it needs updating
        $media_months = get_transient('wpcom_media_months_array');

        // Make sure month and year exists in transient before comparing
        $cached_latest_year = !empty($media_months[0]->year) ? $media_months[0]->year : '';
        $cached_latest_month = !empty($media_months[0]->month) ? $media_months[0]->month : '';

        // If the transient exists, and the attachment uploaded doesn't match the first (latest) month or year in the transient, lets clear it.
        $matches_latest_year = get_the_time('Y', $post_id) === $cached_latest_year;
        $matches_latest_month = get_the_time('n', $post_id) === $cached_latest_month;

        if (false !== $media_months && (!$matches_latest_year || !$matches_latest_month)) {
            // the new attachment is not in the same month/year as the data in our transient
            delete_transient('wpcom_media_months_array');
        }
    }

    /**
     * @see https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/performance/vip-tweaks.php#L65
     *
     * @return array|mixed|object|null
     */
    public function media_library_months_with_files()
    {
        global $wpdb;

        $months = get_transient('wpcom_media_months_array');

        if (false === $months) {
            $months = $wpdb->get_results($wpdb->prepare("
            		     SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
            			FROM $wpdb->posts
            			WHERE post_type = %s
            			ORDER BY post_date DESC
        			", 'attachment'));
            set_transient('wpcom_media_months_array', $months);
        }

        return $months;
    }

    /**
     * @param array $mimes
     *
     * @return array
     */
    public function allow_svg($mimes)
    {
        $mimes['svg'] = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';

        return $mimes;
    }

    /**
     * @param array|null  $data
     * @param static|null $file
     * @param string|null $filename
     * @param array|null  $mimes
     *
     * @return array|null
     */
    public function fix_mime_type_svg($data = null, $file = null, $filename = null, $mimes = null)
    {
        $ext = !empty($data['ext']) ? $data['ext'] : '';
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
     * @param array        $image
     * @param int          $id
     * @param string|array $size
     *
     * @return array
     */
    public function resize_image_on_the_fly($image, $id, $size)
    {
        $path = get_attached_file($id);

        if (\is_array($size) && file_exists($path)) {
            $upload = wp_upload_dir();
            $path_info = pathinfo($path);
            $base_url = $upload['baseurl'].str_replace($upload['basedir'], '', $path_info['dirname']);

            list($width, $height) = $size;
            $meta = wp_get_attachment_metadata($id);

            foreach ($meta['sizes'] as $key => $value) {
                if ((int) $value['width'] === (int) $width && (int) $value['height'] === (int) $height) {
                    return $image;
                }
            }

            // Generate new size
            $resized = image_make_intermediate_size($path, $width, $height, true);

            if ($resized && !is_wp_error($resized)) {
                $key = sprintf('resized-%dx%d', $resized['width'], $resized['height']);
                $meta['sizes'][$key] = $resized;
                wp_update_attachment_metadata($id, $meta);

                $image[0] = "{$base_url}/{$resized['file']}";
                $image[1] = $resized['width'];
                $image[2] = $resized['height'];
            }
        }

        return $image;
    }
}
