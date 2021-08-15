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
class WPQuery implements AutoloadInterface
{
    /**
     * @var string
     */
    public const INVALIDATE_TIME_KEY = 'invalidate-time';

    /**
     * @var string
     */
    public const FOUND_POSTS_KEY = 'found-posts';
    /**
     * @var string
     */
    private $queryHash;

    public function load()
    {
        add_action('save_post', [$this, 'flushFoundRowsCach']);

        add_filter('pre_get_posts', [$this, 'setQueryParams'], 10, 1);
        add_filter('posts_clauses_request', [$this, 'postsClausesRequest'], 10, 2);
    }

    /**
     * @param array<string,string> $clauses
     *
     * @return array<string,string>
     */
    public function postsClausesRequest(array $clauses, WP_Query $query): array
    {
        global $wpdb;

        if ($query->is_main_query()) {
            return $clauses;
        }

        $limit = (int) $query->get('posts_per_page');

        $this->invalidateFoundPostsCache();
        $postIds = $this->getFoundPostsCache();

        if (false === $postIds) {
            try {
                $pdo = app_db_pdo();

                $where = $clauses['where'] ?? '';
                $join = $clauses['join'] ?? '';

                /** @noinspection SqlConstantCondition */
                $idsStatement = $pdo->prepare(
                    "SELECT $wpdb->posts.ID FROM $wpdb->posts $join WHERE 1=1 $where GROUP BY $wpdb->posts.ID ORDER BY $wpdb->posts.post_date"
                );

                $idsStatement->execute();

                /** @var int[] $postIds */
                $postIds = $idsStatement->fetchAll(PDO::FETCH_COLUMN);

                $this->setFoundPostsCache($postIds);
            } catch (Exception $exception) {
                app_error_log($exception, __METHOD__);
            }
        }

        if ( ! empty($postIds)) {
            $query->found_posts = count((array) $postIds);
            if ( ! empty($clauses['limits'])) {
                $query->max_num_pages = (int) ceil($query->found_posts / $limit);
            }
        }

        return $clauses;
    }

    private function invalidateFoundPostsCache(): void
    {
        $globalInvalidateTime = $this->getInvalidateTime();
        $localInvalidateTime = $this->getTimeFoundPosts();

        if ($localInvalidateTime < $globalInvalidateTime) {
            wp_cache_delete($this->generateFoundPostCacheKey(), Cache::QUERY_CACHE_GROUP);
            wp_cache_delete($this->generateFoundPostCacheKey(true), Cache::QUERY_CACHE_GROUP);
        }
    }

    private function getInvalidateTime(): int
    {
        return (int) wp_cache_get(self::INVALIDATE_TIME_KEY, Cache::QUERY_CACHE_GROUP);
    }

    private function getTimeFoundPosts(): int
    {
        return (int) wp_cache_get($this->generateFoundPostCacheKey(true), Cache::QUERY_CACHE_GROUP);
    }

    private function generateFoundPostCacheKey(bool $addTime = false): string
    {
        return sprintf('%s%s-%s', $addTime ? 'time-' : '', self::FOUND_POSTS_KEY, $this->queryHash);
    }

    /**
     * @return bool|int[]
     */
    private function getFoundPostsCache()
    {
        return wp_cache_get($this->generateFoundPostCacheKey(), Cache::QUERY_CACHE_GROUP);
    }

    /**
     * @param int[] $postIds
     */
    private function setFoundPostsCache(array $postIds): void
    {
        wp_cache_set($this->generateFoundPostCacheKey(), $postIds, Cache::QUERY_CACHE_GROUP);
        wp_cache_set($this->generateFoundPostCacheKey(true), time(), Cache::QUERY_CACHE_GROUP);
    }

    public function setQueryParams(WP_Query $query): void
    {
        if ( ! $query->is_main_query()) {
            $limit = (int) $query->get('posts_per_page');

            $orderby = $query->get('orderby', false);

            $query->set('no_found_rows', true);
            $query->set('showposts', null);
            $query->set('posts_per_archive_page', null);
            $query->set('ignore_sticky_posts', true);

            $this->queryHash = md5(serialize($query->query_vars));

            if ('rand' === $orderby) {
                $postIds = $this->getFoundPostsCache();

                if ( ! empty($postIds)) {
                    /** @var int[] $postIds */
                    if (empty($query->get('post__in'))) {
                        shuffle($postIds);

                        if ($limit > 0) {
                            $postIds = array_slice($postIds, 0, $limit);
                        }

                        $query->set('post__in', $postIds);
                    }

                    if ( ! empty($query->query_vars['post__in'])) {
                        shuffle($query->query_vars['post__in']);
                    }

                    $query->set('orderby', 'post__in');
                }
            }
        }
    }

	/**
	 * @param  int  $postId
	 */
    public function flushFoundRowsCach(int $postId): void
    {
        wp_cache_set(self::INVALIDATE_TIME_KEY, time(), Cache::QUERY_CACHE_GROUP);
    }
}
