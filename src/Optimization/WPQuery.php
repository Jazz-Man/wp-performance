<?php

namespace JazzMan\Performance\Optimization;

use Exception;
use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\Utils\Cache;
use PDO;
use WP_Query;

/**
 * Class WP_Query.
 */
class WPQuery implements AutoloadInterface {
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
        add_action('save_post', [$this, 'flushFoundRowsCach']);

        add_filter('pre_get_posts', [$this, 'setQueryParams'], 10, 1);
        add_filter('posts_clauses_request', [$this, 'postsClausesRequest'], 10, 2);
    }

    /**
     * @param array<string,string> $clauses
     *
     * @return array<string,string>
     */
    public function postsClausesRequest(array $clauses, WP_Query $wpQuery): array {
        global $wpdb;

        if ($wpQuery->is_main_query()) {
            return $clauses;
        }

        $limit = (int) $wpQuery->get('posts_per_page');

        $this->invalidateFoundPostsCache();
        $postIds = $this->getFoundPostsCache();

        if (!empty($postIds)) {
            try {
                $pdo = app_db_pdo();

                /** @noinspection SqlConstantCondition */
                $pdoStatement = $pdo->prepare(
                    sprintf(
                        'SELECT %s.ID FROM %s %s WHERE 1=1 %s GROUP BY %s.ID ORDER BY %s.post_date',
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
                $postIds = $pdoStatement->fetchAll(PDO::FETCH_COLUMN);

                $this->setFoundPostsCache($postIds);
            } catch (Exception $exception) {
                app_error_log($exception, __METHOD__);
            }
        }

        if ( ! empty($postIds)) {
            $wpQuery->found_posts = count($postIds);

            if ( ! empty($clauses['limits'])) {
                $wpQuery->max_num_pages = (int) ceil($wpQuery->found_posts / $limit);
            }
        }

        return $clauses;
    }

    private function invalidateFoundPostsCache(): void {
        $globalInvalidateTime = $this->getInvalidateTime();
        $timeFoundPosts = $this->getTimeFoundPosts();

        if ($timeFoundPosts < $globalInvalidateTime) {
            wp_cache_delete($this->generateFoundPostCacheKey(), Cache::QUERY_CACHE_GROUP);
            wp_cache_delete($this->generateFoundPostCacheKey(true), Cache::QUERY_CACHE_GROUP);
        }
    }

    private function getInvalidateTime(): int {
        return (int) wp_cache_get(self::INVALIDATE_TIME_KEY, Cache::QUERY_CACHE_GROUP);
    }

    private function getTimeFoundPosts(): int {
        return (int) wp_cache_get($this->generateFoundPostCacheKey(true), Cache::QUERY_CACHE_GROUP);
    }

    private function generateFoundPostCacheKey(bool $addTime = false): string {
        return sprintf('%s%s-%s', $addTime ? 'time-' : '', self::FOUND_POSTS_KEY, $this->queryHash);
    }

    /**
     * @return int[]
     */
    private function getFoundPostsCache(): array {
        return (array) wp_cache_get($this->generateFoundPostCacheKey(), Cache::QUERY_CACHE_GROUP);
    }

    /**
     * @param int[] $postIds
     */
    private function setFoundPostsCache(array $postIds): void {
        wp_cache_set($this->generateFoundPostCacheKey(), $postIds, Cache::QUERY_CACHE_GROUP);
        wp_cache_set($this->generateFoundPostCacheKey(true), time(), Cache::QUERY_CACHE_GROUP);
    }

    public function setQueryParams(WP_Query $wpQuery): void {
        if ( ! $wpQuery->is_main_query()) {
            $limit = (int) $wpQuery->get('posts_per_page');

            /** @var string|false $orderby */
            $orderby = $wpQuery->get('orderby', false);

            $wpQuery->set('no_found_rows', true);
            $wpQuery->set('showposts', null);
            $wpQuery->set('posts_per_archive_page', null);
            $wpQuery->set('ignore_sticky_posts', true);

            $this->queryHash = md5(serialize($wpQuery->query_vars));

            if ('rand' === $orderby) {
                $postIds = $this->getFoundPostsCache();

                if ( ! empty($postIds)) {
                    if (empty($wpQuery->get('post__in'))) {
                        shuffle($postIds);

                        if ($limit > 0) {
                            $postIds = array_slice($postIds, 0, $limit);
                        }

                        $wpQuery->set('post__in', $postIds);
                    }

                    if ( ! empty($wpQuery->query_vars['post__in'])) {
                        /** @var int[] $postIn */
                        $postIn = $wpQuery->query_vars['post__in'];

                        shuffle($postIn);

                        $wpQuery->set('post__in', $postIn);
                    }

                    $wpQuery->set('orderby', 'post__in');
                }
            }
        }
    }

    public function flushFoundRowsCach(int $postId): void {
        wp_cache_set(self::INVALIDATE_TIME_KEY, time(), Cache::QUERY_CACHE_GROUP);
    }
}
