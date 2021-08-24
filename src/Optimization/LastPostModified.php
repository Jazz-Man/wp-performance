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
class LastPostModified implements AutoloadInterface {
    /**
     * @var int
     */
    public const LOCK_TIME_IN_SECONDS = 30;

    /**
     * @var string
     */
    public const OPTION_PREFIX = 'lastpostmodified';

    public function load(): void {
        add_filter('pre_get_lastpostmodified', fn (bool $boolean, string $timezone, string $postType) => $this->overrideGetLastPostModified($boolean, $timezone, $postType), 10, 3);
        add_action('transition_post_status', function (string $newStatus, string $oldStatus, WP_Post $post): void {
            $this->transitionPostStatus($newStatus, $oldStatus, $post);
        }, 10, 3);
    }

    public function transitionPostStatus(string $newStatus, string $oldStatus, WP_Post $wpPost): void {
        if ( ! in_array('publish', [$oldStatus, $newStatus], true)) {
            return;
        }

        /** @var string[] $publicPostTypes */
        $publicPostTypes = get_post_types(['public' => true]);

        if ( ! in_array($wpPost->post_type, $publicPostTypes, true)) {
            return;
        }

        if ($this->isLocked($wpPost->post_type)) {
            return;
        }
        $this->bumpLastPostModified($wpPost);
    }

    private function isLocked(string $postType): bool {
        $key = $this->getLockName($postType);

        // if the add fails, then we already have a lock set
        return false === wp_cache_add($key, 1, Cache::CACHE_GROUP, self::LOCK_TIME_IN_SECONDS);
    }

    private function getLockName(string $postType): string {
        return sprintf('%s_%s_lock', self::OPTION_PREFIX, $postType);
    }

    private function bumpLastPostModified(WP_Post $wpPost): void {
        // Update default of `any`
        $this->updateLastPostModified($wpPost->post_modified_gmt, 'gmt');
        $this->updateLastPostModified($wpPost->post_modified_gmt, 'server');
        $this->updateLastPostModified($wpPost->post_modified, 'blog');
        // Update value for post_type
        $this->updateLastPostModified($wpPost->post_modified_gmt, 'gmt', $wpPost->post_type);
        $this->updateLastPostModified($wpPost->post_modified_gmt, 'server', $wpPost->post_type);
        $this->updateLastPostModified($wpPost->post_modified, 'blog', $wpPost->post_type);
    }

    public function updateLastPostModified(string $time, string $timezone, string $postType = 'any'): bool {
        return update_option($this->getOptionName($timezone, $postType), $time, false);
    }

    private function getOptionName(string $timezone, string $postType): string {
        return sprintf('%s_%s_%s', self::OPTION_PREFIX, strtolower($timezone), $postType);
    }

    /**
     * @return bool|string
     */
    public function overrideGetLastPostModified(bool $boolean, string $timezone, string $postType) {
        /** @var string|false $lastPostModified */
        $lastPostModified = $this->getLastPostModified($timezone, $postType);

        if (false === $lastPostModified) {
            return $boolean;
        }

        return $lastPostModified;
    }

    /**
     * @return mixed
     */
    private function getLastPostModified(string $timezone, string $postType) {
        return get_option($this->getOptionName($timezone, $postType), false);
    }
}
