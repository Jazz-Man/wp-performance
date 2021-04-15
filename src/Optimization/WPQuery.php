<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\Utils\Cache;
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

        add_filter('woocommerce_install_skip_create_files', '__return_true');
    }

    public function postsClausesRequest(array $clauses, WP_Query $wp_query): array
    {
        if ($wp_query->is_main_query()) {
            return $clauses;
        }

        $limit = $wp_query->get('posts_per_page');

        $this->invalidateFoundPostsCache();
        $ids = $this->getFoundPostsCache();

        if (false === $ids) {
            global $wpdb;

            $pdo = app_db_pdo();

            $where = $clauses['where'] ?? '';
            $join = $clauses['join'] ?? '';

            $ids_st = $pdo->prepare(
                "SELECT {$wpdb->posts}.ID FROM {$wpdb->posts} {$join} WHERE 1=1 {$where} GROUP BY {$wpdb->posts}.ID ORDER BY {$wpdb->posts}.post_date"
            );

            $ids_st->execute();

            $ids = $ids_st->fetchAll(\PDO::FETCH_COLUMN);

            $this->setFoundPostsCache($ids);
        }

        $wp_query->found_posts = \count($ids);
        if (!empty($clauses['limits'])) {
            $wp_query->max_num_pages = \ceil($wp_query->found_posts / $limit);
        }

        return $clauses;
    }

    public function setQueryParams(WP_Query $wp_query)
    {
        if (!$wp_query->is_main_query()) {
            $limit = $wp_query->get('posts_per_page');

            $orderby = $wp_query->get('orderby', false);

            $wp_query->set('no_found_rows', true);
            $wp_query->set('showposts', null);
            $wp_query->set('posts_per_archive_page', null);
            $wp_query->set('ignore_sticky_posts', true);

            $this->queryHash = \md5(\serialize($wp_query->query_vars));

            if ('rand' === $orderby) {
                $ids = $this->getFoundPostsCache();

                if ($ids) {
                    if (empty($wp_query->get('post__in'))) {
                        \shuffle($ids);

                        if ($limit > 0) {
                            $ids = \array_slice($ids, 0, $limit);
                        }

                        $wp_query->set('post__in', $ids);
                    } else {
                        \shuffle($wp_query->query_vars['post__in']);
                    }

                    $wp_query->set('orderby', 'post__in');
                }
            }
        }
    }

    public function flushFoundRowsCach(int $post_id): void
    {
        wp_cache_set(self::INVALIDATE_TIME_KEY, \time(), Cache::QUERY_CACHE_GROUP);
    }

    private function invalidateFoundPostsCache(): void
    {
        $globalInvalidateTime = $this->getInvalidateTime();
        $localInvalidateTime = $this->getTimeFoundPosts();

        if ($localInvalidateTime && $localInvalidateTime < $globalInvalidateTime) {
            wp_cache_delete(sprintf('%s-%s', self::FOUND_POSTS_KEY, $this->queryHash), Cache::QUERY_CACHE_GROUP);
            wp_cache_delete(sprintf('time-%s-%s', self::FOUND_POSTS_KEY, $this->queryHash), Cache::QUERY_CACHE_GROUP);
        }
    }

    /**
     * @return bool|mixed
     */
    private function getFoundPostsCache()
    {
        return wp_cache_get(sprintf('%s-%s', self::FOUND_POSTS_KEY, $this->queryHash), Cache::QUERY_CACHE_GROUP);
    }

    private function setFoundPostsCache(array $ids)
    {
        wp_cache_set(sprintf('%s-%s', self::FOUND_POSTS_KEY, $this->queryHash), $ids, Cache::QUERY_CACHE_GROUP);
        wp_cache_set(
            sprintf('time-%s-%s', self::FOUND_POSTS_KEY, $this->queryHash),
            \time(),
            Cache::QUERY_CACHE_GROUP
        );
    }

    /**
     * @return bool|mixed
     */
    private function getInvalidateTime()
    {
        return wp_cache_get(self::INVALIDATE_TIME_KEY, Cache::QUERY_CACHE_GROUP);
    }

    /**
     * @return bool|mixed
     */
    private function getTimeFoundPosts()
    {
        return wp_cache_get(sprintf('time-%s-%s', self::FOUND_POSTS_KEY, $this->queryHash), Cache::QUERY_CACHE_GROUP);
    }
}
