<?php

namespace JazzMan\Performance\Optimization;

use Exception;
use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\Utils\Cache;
use PDO;
use WP_Query;
use wpdb;

/**
 * Class WP_Query.
 */
final class WPQuery implements AutoloadInterface {

    /**
     * @var string
     */
    public const INVALIDATE_TIME_KEY = 'invalidate-time';

    /**
     * @var string
     */
    public const FOUND_POSTS_KEY = 'found-posts';

    private ?string $queryHash = null;

    public function load(): void {
        add_action( 'save_post', static function (): void {
            wp_cache_set( self::INVALIDATE_TIME_KEY, time(), Cache::QUERY_CACHE_GROUP );
        } );

        add_action( 'pre_get_posts', $this->setQueryParams( ... ), 10, 1 );
        add_filter( 'posts_clauses_request', $this->postsClausesRequest( ... ), 10, 2 );
    }

    /**
     * @param array{where: ?string, groupby: ?string, join: ?string, orderby: ?string, distinct: ?string, fields: ?string, limits: ?string} $clauses
     *
     * @return array{where: ?string, groupby: ?string, join: ?string, orderby: ?string, distinct: ?string, fields: ?string, limits: ?string}
     */
    public function postsClausesRequest( array $clauses, WP_Query $wpQuery ): array {
        /** @var wpdb $wpdb */
        global $wpdb;

        if ( $wpQuery->is_main_query() ) {
            return $clauses;
        }

        /** @var int $limit */
        $limit = absint( (int) $wpQuery->get( 'posts_per_page', 10 ) );

        $this->invalidateFoundPostsCache();

        /** @var bool|int[] $postIds */
        $postIds = $this->getFoundPostsCache();

        if ( ! empty( $postIds ) ) {
            try {
                $pdo = app_db_pdo();

                /** @noinspection SqlConstantCondition */
                $pdoStatement = $pdo->prepare(
                    \sprintf(
                        'select %s.ID from %s %s where 1=1 %s group by %s.ID order by %s.post_date',
                        $wpdb->posts,
                        $wpdb->posts,
                        $clauses['join'] ?? '',
                        $clauses['where'] ?? '',
                        $wpdb->posts,
                        $wpdb->posts
                    )
                );

                $pdoStatement->execute();

                /** @var int[] $postIds */
                $postIds = $pdoStatement->fetchAll( PDO::FETCH_COLUMN );

                $this->setFoundPostsCache( $postIds );
            } catch ( Exception $exception ) {
                app_error_log( $exception, __METHOD__ );
            }
        }

        if ( ! empty( $postIds ) ) {
            /** @var int[] $postIds */
            $wpQuery->found_posts = \count( $postIds );

            if ( ! empty( $clauses['limits'] ) ) {
                $wpQuery->max_num_pages = (int) ceil( $wpQuery->found_posts / $limit );
            }
        }

        return $clauses;
    }

    public function setQueryParams( WP_Query $wpQuery ): void {
        if ( ! $wpQuery->is_main_query() ) {

            /** @var int $limit */
            $limit = absint( $wpQuery->get( 'posts_per_page', 10 ) );

            /** @var false|string $orderby */
            $orderby = $wpQuery->get( 'orderby', false );

            $wpQuery->set( 'no_found_rows', true );
            $wpQuery->set( 'showposts', null );
            $wpQuery->set( 'posts_per_archive_page', null );
            $wpQuery->set( 'ignore_sticky_posts', true );

            $this->queryHash = md5( serialize( $wpQuery->query_vars ) );

            if ( 'rand' === $orderby ) {
                /** @var bool|int[] $postIds */
                $postIds = $this->getFoundPostsCache();

                if ( ! empty( $postIds ) ) {
                    if ( empty( $wpQuery->get( 'post__in' ) ) ) {
                        /** @var int[] $postIds */
                        shuffle( $postIds );

                        if ( 0 < $limit ) {
                            $postIds = \array_slice( $postIds, 0, $limit );
                        }

                        $wpQuery->set( 'post__in', $postIds );
                    }

                    if ( ! empty( $wpQuery->query_vars['post__in'] ) ) {
                        /** @var int[] $postIn */
                        $postIn = $wpQuery->get( 'post__in' );

                        shuffle( $postIn );

                        $wpQuery->set( 'post__in', $postIn );
                    }

                    $wpQuery->set( 'orderby', 'post__in' );
                }
            }
        }
    }

    private function invalidateFoundPostsCache(): void {
        $globalInvalidateTime = $this->getInvalidateTime();

        if ( empty( $globalInvalidateTime ) ) {
            return;
        }

        $timeFoundPosts = $this->getTimeFoundPosts();

        if ( empty( $timeFoundPosts ) ) {
            return;
        }

        if ( $timeFoundPosts < $globalInvalidateTime ) {
            wp_cache_delete( $this->generateFoundPostCacheKey(), Cache::QUERY_CACHE_GROUP );
            wp_cache_delete( $this->generateFoundPostCacheKey( true ), Cache::QUERY_CACHE_GROUP );
        }
    }

    /**
     * @return false|int
     */
    private function getInvalidateTime(): bool|int {
        /** @var false|int $time */
        $time = wp_cache_get( self::INVALIDATE_TIME_KEY, Cache::QUERY_CACHE_GROUP );

        if ( ! $time ) {
            return false;
        }

        return $time;
    }

    /**
     * @return false|int
     */
    private function getTimeFoundPosts(): bool|int {
        /** @var false|int $time */
        $time = wp_cache_get( $this->generateFoundPostCacheKey( true ), Cache::QUERY_CACHE_GROUP );

        if ( ! $time ) {
            return false;
        }

        return $time;
    }

    private function generateFoundPostCacheKey( bool $addTime = false ): string {
        return \sprintf(
            '%s%s-%s',
            $addTime ? 'time-' : '',
            self::FOUND_POSTS_KEY,
            $this->queryHash ?: ''
        );
    }

    private function getFoundPostsCache(): mixed {
        return wp_cache_get( $this->generateFoundPostCacheKey(), Cache::QUERY_CACHE_GROUP );
    }

    /**
     * @param int[] $postIds
     */
    private function setFoundPostsCache( array $postIds ): void {
        wp_cache_set( $this->generateFoundPostCacheKey(), $postIds, Cache::QUERY_CACHE_GROUP );
        wp_cache_set( $this->generateFoundPostCacheKey( true ), time(), Cache::QUERY_CACHE_GROUP );
    }
}
