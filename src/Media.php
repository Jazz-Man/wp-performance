<?php

namespace JazzMan\Performance;

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

        add_action('ini', [$this, 'enable_performance_tweaks']);

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

    public function enable_performance_tweaks()
    {
        // This disables the adjacent_post links in the header that are almost never beneficial and are very slow to compute.
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
    }

    /**
     * @see https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/performance/vip-tweaks.php#L39
     *
     * @param int $post_id
     */
    public function bust_media_months_cache($post_id)
    {
        if (\defined('WP_IMPORTING') && WP_IMPORTING) {
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
}
