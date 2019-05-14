<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_Post;

/**
 * Class LastPostModified.
 *
 * @see https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/performance/lastpostmodified.php
 */
class LastPostModified implements AutoloadInterface
{
    const DEFAULT_TIMEZONE = 'gmt';
    const LOCK_TIME_IN_SECONDS = 30;
    const OPTION_PREFIX = 'lastpostmodified';

    public function load()
    {
        add_action('init', [$this, 'init']);
    }

    public function init()
    {
        add_filter('pre_get_lastpostmodified', [$this, 'overrideGetLastPostModified'], 10, 3);
        add_action('transition_post_status', [$this, 'transitionPostStatus'], 10, 3);
    }

    /**
     * @param string  $new_status
     * @param string  $old_status
     * @param WP_Post $post
     */
    public function transitionPostStatus(string $new_status, string $old_status, WP_Post $post)
    {
        if (!\in_array('publish', [$old_status, $new_status])) {
            return;
        }
        $public_post_types = get_post_types(['public' => true]);
        if (!\in_array($post->post_type, $public_post_types)) {
            return;
        }
        $is_locked = $this->isLocked($post->post_type);
        if ($is_locked) {
            return;
        }
        $this->bumpLastPostModified($post);
    }

    /**
     * @param string $post_type
     *
     * @return bool
     */
    private function isLocked(string $post_type)
    {
        $key = $this->getLockName($post_type);

        // if the add fails, then we already have a lock set
        return false === wp_cache_add($key, 1, false, self::LOCK_TIME_IN_SECONDS);
    }

    /**
     * @param \WP_Post $post
     */
    private function bumpLastPostModified(WP_Post $post)
    {
        // Update default of `any`
        $this->updateLastPostModified($post->post_modified_gmt, 'gmt');
        $this->updateLastPostModified($post->post_modified_gmt, 'server');
        $this->updateLastPostModified($post->post_modified, 'blog');
        // Update value for post_type
        $this->updateLastPostModified($post->post_modified_gmt, 'gmt', $post->post_type);
        $this->updateLastPostModified($post->post_modified_gmt, 'server', $post->post_type);
        $this->updateLastPostModified($post->post_modified, 'blog', $post->post_type);
    }

    /**
     * @param string $post_type
     *
     * @return string
     */
    private function getLockName(string $post_type)
    {
        return sprintf('%s_%s_lock', self::OPTION_PREFIX, $post_type);
    }

    /**
     * @param string $time
     * @param string $timezone
     * @param string $post_type
     *
     * @return bool
     */
    public function updateLastPostModified(string $time, string $timezone, string $post_type = 'any')
    {
        $option_name = $this->getOptionName($timezone, $post_type);

        return update_option($option_name, $time, false);
    }

    /**
     * @param string $timezone
     * @param string $post_type
     *
     * @return string
     */
    private function getOptionName(string $timezone, string $post_type)
    {
        $timezone = strtolower($timezone);

        return sprintf('%s_%s_%s', self::OPTION_PREFIX, $timezone, $post_type);
    }

    /**
     * @param bool   $boolean
     * @param string $timezone
     * @param string $post_type
     *
     * @return bool|mixed
     */
    public function overrideGetLastPostModified(bool $boolean, string $timezone, string $post_type)
    {
        $stored_lastpostmodified = $this->getLastPostModified($timezone, $post_type);
        if (false === $stored_lastpostmodified) {
            return $boolean;
        }

        return $stored_lastpostmodified;
    }

    /**
     * @param string $timezone
     * @param string $post_type
     *
     * @return mixed
     */
    public function getLastPostModified($timezone, $post_type)
    {
        $option_name = $this->getOptionName($timezone, $post_type);

        return get_option($option_name);
    }
}
