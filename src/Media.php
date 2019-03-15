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
        if ( ! App::enabled()) {
            return $avatar;
        }

        // Swap out the file for a base64 encoded image.
        $image  = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
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
        if ( ! App::enabled()) {
            return $avatar_list;
        }

        // Remove images.
        $avatar_list = preg_replace('|<img([^>]+)> |i', '', $avatar_list);

        // Send back the list.
        return $avatar_list;
    }
}
