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

    public function load() {
        add_filter( 'pre_get_lastpostmodified', [ __CLASS__, 'overrideGetLastPostModified' ], 10, 3 );
        add_action( 'transition_post_status', [ __CLASS__, 'transitionPostStatus' ], 10, 3 );
    }

    public static function transitionPostStatus(string $newStatus, string $oldStatus, WP_Post $wpPost): void {
        if ( ! in_array( 'publish', [ $oldStatus, $newStatus ], true ) ) {
            return;
        }

        /** @var string[] $publicPostTypes */
        $publicPostTypes = get_post_types( [ 'public' => true ] );

        if ( ! in_array( $wpPost->post_type, $publicPostTypes, true ) ) {
            return;
        }

        if ( self::isLocked( $wpPost->post_type ) ) {
            return;
        }

        self::bumpLastPostModified( $wpPost );
    }

    private static function isLocked(string $postType): bool {
        $key = self::getLockName( $postType );

        // if the add fails, then we already have a lock set
        return false === wp_cache_add( $key, 1, Cache::CACHE_GROUP, self::LOCK_TIME_IN_SECONDS );
    }

    private static function getLockName(string $postType): string {
        return sprintf( '%s_%s_lock', self::OPTION_PREFIX, $postType );
    }

    private static function bumpLastPostModified(WP_Post $wpPost): void {
        // Update default of `any`
        self::updateLastPostModified( $wpPost->post_modified_gmt, 'gmt' );
        self::updateLastPostModified( $wpPost->post_modified_gmt, 'server' );
        self::updateLastPostModified( $wpPost->post_modified, 'blog' );
        // Update value for post_type
        self::updateLastPostModified( $wpPost->post_modified_gmt, 'gmt', $wpPost->post_type );
        self::updateLastPostModified( $wpPost->post_modified_gmt, 'server', $wpPost->post_type );
        self::updateLastPostModified( $wpPost->post_modified, 'blog', $wpPost->post_type );
    }

    public static function updateLastPostModified(string $time, string $timezone, string $postType = 'any'): bool {
        return update_option( self::getOptionName( $timezone, $postType ), $time, false );
    }

    private static function getOptionName(string $timezone, string $postType): string {
        return sprintf( '%s_%s_%s', self::OPTION_PREFIX, strtolower( $timezone ), $postType );
    }

    /**
     * @return bool|string
     */
    public static function overrideGetLastPostModified(bool $boolean, string $timezone, string $postType) {
        /** @var string|false $lastPostModified */
        $lastPostModified = self::getLastPostModified( $timezone, $postType );

        return false === $lastPostModified ? $boolean : $lastPostModified;
    }

    /**
     * @return mixed
     */
    private static function getLastPostModified(string $timezone, string $postType) {
        return get_option( self::getOptionName( $timezone, $postType ), false );
    }
}
