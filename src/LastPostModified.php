<?php

namespace JazzMan\Performance;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_Post;

/**
 * Class LastPostModified.
 * @see https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/performance/lastpostmodified.php
 */
class LastPostModified implements AutoloadInterface
{

    const OPTION_PREFIX = 'lastpostmodified';

    const DEFAULT_TIMEZONE = 'gmt';

    const LOCK_TIME_IN_SECONDS = 30;

    public function load()
    {
        add_action('init', [$this, 'init']);
    }

    public function init()
    {
        add_filter('pre_get_lastpostmodified', [$this, 'override_get_lastpostmodified'], 10, 3);
        add_action('transition_post_status', [$this, 'handle_post_transition'], 10, 3);
        add_action('bump_lastpostmodified', [$this, 'bump_lastpostmodified']);
    }

    /**
     * @param string  $new_status
     * @param string  $old_status
     * @param WP_Post $post
     */
    public function handle_post_transition($new_status, $old_status, $post)
    {
        if ( ! \in_array('publish', [$old_status, $new_status])) {
            return;
        }
        $public_post_types = get_post_types(['public' => true]);
        if ( ! \in_array($post->post_type, $public_post_types)) {
            return;
        }
        $is_locked = $this->is_locked($post->post_type);
        if ($is_locked) {
            return;
        }
        do_action('bump_lastpostmodified', $post);
    }

    /**
     * @param string $lastpostmodified
     * @param string $timezone
     * @param string $post_type
     *
     * @return mixed|string
     */
    public function override_get_lastpostmodified($lastpostmodified, $timezone, $post_type)
    {
        $stored_lastpostmodified = $this->get_lastpostmodified($timezone, $post_type);
        if (false === $stored_lastpostmodified) {
            return $lastpostmodified;
        }

        return $stored_lastpostmodified;
    }

    /**
     * @param WP_Post $post
     */
    public function bump_lastpostmodified($post)
    {
        // Update default of `any`
        $this->update_lastpostmodified($post->post_modified_gmt, 'gmt');
        $this->update_lastpostmodified($post->post_modified_gmt, 'server');
        $this->update_lastpostmodified($post->post_modified, 'blog');
        // Update value for post_type
        $this->update_lastpostmodified($post->post_modified_gmt, 'gmt', $post->post_type);
        $this->update_lastpostmodified($post->post_modified_gmt, 'server', $post->post_type);
        $this->update_lastpostmodified($post->post_modified, 'blog', $post->post_type);
    }

    /**
     * @param string $timezone
     * @param string $post_type
     *
     * @return mixed
     */
    public function get_lastpostmodified($timezone, $post_type)
    {
        $option_name = $this->get_option_name($timezone, $post_type);

        return get_option($option_name);
    }

    /**
     * @param string $time
     * @param string $timezone
     * @param string $post_type
     *
     * @return bool
     */
    public function update_lastpostmodified($time, $timezone, $post_type = 'any')
    {
        $option_name = $this->get_option_name($timezone, $post_type);

        return update_option($option_name, $time, false);
    }

    /**
     * @param string $post_type
     *
     * @return bool
     */
    private function is_locked($post_type)
    {
        $key = $this->get_lock_name($post_type);

        // if the add fails, then we already have a lock set
        return false === wp_cache_add($key, 1, false, self::LOCK_TIME_IN_SECONDS);
    }

    /**
     * @param string $post_type
     *
     * @return string
     */
    private function get_lock_name($post_type)
    {
        return sprintf('%s_%s_lock', self::OPTION_PREFIX, $post_type);
    }

    /**
     * @param string $timezone
     * @param string $post_type
     *
     * @return string
     */
    private function get_option_name($timezone, $post_type)
    {
        $timezone = strtolower($timezone);

        return sprintf('%s_%s_%s', self::OPTION_PREFIX, $timezone, $post_type);
    }
}
