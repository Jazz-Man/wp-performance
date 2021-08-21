<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\Utils\Cache;
use WP_Post;

/**
 * Class LastPostModified.
 *
 * @see https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/performance/lastpostmodified.php
 */
class LastPostModified implements AutoloadInterface
{
    public const LOCK_TIME_IN_SECONDS = 30;
    public const OPTION_PREFIX = 'lastpostmodified';

    /**
     * @return void
     */
    public function load()
    {
        add_filter('pre_get_lastpostmodified', [$this, 'overrideGetLastPostModified'], 10, 3);
        add_action('transition_post_status', [$this, 'transitionPostStatus'], 10, 3);
    }

    /**
     * @return void
     */
    public function transitionPostStatus(string $newStatus, string $oldStatus, WP_Post $post)
    {
        if ( ! in_array('publish', [$oldStatus, $newStatus])) {
            return;
        }
        $publicPostTypes = get_post_types(['public' => true]);
        if ( ! in_array($post->post_type, $publicPostTypes)) {
            return;
        }

        if ($this->isLocked($post->post_type)) {
            return;
        }
        $this->bumpLastPostModified($post);
    }

    private function isLocked(string $postType): bool
    {
        $key = $this->getLockName($postType);

        // if the add fails, then we already have a lock set
        return false === wp_cache_add($key, 1, Cache::CACHE_GROUP, self::LOCK_TIME_IN_SECONDS);
    }

    private function getLockName(string $postType): string
    {
        return sprintf('%s_%s_lock', self::OPTION_PREFIX, $postType);
    }

    private function bumpLastPostModified(WP_Post $post): void
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

    public function updateLastPostModified(string $time, string $timezone, string $postType = 'any'): bool
    {
        return update_option($this->getOptionName($timezone, $postType), $time, false);
    }

    private function getOptionName(string $timezone, string $postType): string
    {
        return sprintf('%s_%s_%s', self::OPTION_PREFIX, strtolower($timezone), $postType);
    }

    /**
     * @return bool|mixed
     */
    public function overrideGetLastPostModified(bool $boolean, string $timezone, string $postType)
    {
        $lastPostModified = $this->getLastPostModified($timezone, $postType);
        if (false === $lastPostModified) {
            return $boolean;
        }

        return $lastPostModified;
    }

    /**
     * @return mixed
     */
    private function getLastPostModified(string $timezone, string $postType)
    {
        return get_option($this->getOptionName($timezone, $postType), false);
    }
}
