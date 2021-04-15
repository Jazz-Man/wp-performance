<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_Query;

/**
 * Class WP_Query.
 */
class WPQuery implements AutoloadInterface
{
    /**
     * @var string
     */
    private $cacheGroup = 'query';
    /**
     * @var string
     */
    private $invalidateTimeKey = 'invalidate-time';

    /**
     * @var string
     */
    private $foundPostsKey = 'found-posts';
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
        wp_cache_set($this->invalidateTimeKey, \time(), $this->cacheGroup);
    }

    private function invalidateFoundPostsCache(): bool
    {
        $global_invalidate_time = $this->getInvalidateTime();
        $local_invalidate_time = $this->getTimeFoundPosts();

        if ($local_invalidate_time && $local_invalidate_time < $global_invalidate_time) {
            wp_cache_delete("{$this->foundPostsKey}-{$this->queryHash}", $this->cacheGroup);
            wp_cache_delete("time-{$this->foundPostsKey}-{$this->queryHash}", $this->cacheGroup);

            return true;
        }

        return false;
    }

    /**
     * @return bool|mixed
     */
    private function getFoundPostsCache()
    {
        return wp_cache_get("{$this->foundPostsKey}-{$this->queryHash}", $this->cacheGroup);
    }

    private function setFoundPostsCache(array $ids)
    {
        wp_cache_set("{$this->foundPostsKey}-{$this->queryHash}", $ids, $this->cacheGroup);
        wp_cache_set("time-{$this->foundPostsKey}-{$this->queryHash}", \time(), $this->cacheGroup);
    }

    /**
     * @return bool|mixed
     */
    private function getInvalidateTime()
    {
        return wp_cache_get($this->invalidateTimeKey, $this->cacheGroup);
    }

    /**
     * @return bool|mixed
     */
    private function getTimeFoundPosts()
    {
        return wp_cache_get("time-{$this->foundPostsKey}-{$this->queryHash}", $this->cacheGroup);
    }
}
