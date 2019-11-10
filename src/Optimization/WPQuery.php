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
    private $cache_group = 'query';
    /**
     * @var string
     */
    private $invalidate_time_key = 'invalidate-time';

    /**
     * @var string
     */
    private $found_posts_key = 'found-posts';

    public function load()
    {

        add_action('save_post', [$this, 'flushFoundRowsCach']);

        add_filter('posts_clauses_request', [$this, 'postsClausesRequest'], 10, 2);

        add_filter('pre_get_posts', [$this, 'setQueryParams'], 10, 1);

        add_filter('woocommerce_install_skip_create_files', '__return_true');
    }

    /**
     * @param array     $clauses
     * @param \WP_Query $query
     *
     * @return array
     */
    public function postsClausesRequest($clauses, WP_Query $query)
    {
        if ($query->is_main_query()) {
            return $clauses;
        }

        $query->set('no_found_rows', true);

        $query_hash = $query->query_vars_hash;

        $orderby = $query->get('orderby', false);
        $limit = $query->get('posts_per_page');

        $this->invalidateFoundPostsCache($query_hash);
        $ids = $this->getFoundPostsCache($query_hash);

        if (false === $ids) {
            global $wpdb;

            $where = $clauses['where'] ?? '';
            $join = $clauses['join'] ?? '';

            $ids = $wpdb->get_col("SELECT {$wpdb->posts}.ID FROM {$wpdb->posts} $join WHERE 1=1 $where GROUP BY {$wpdb->posts}.ID ORDER BY {$wpdb->posts}.post_date");

            $ids = array_map(static function ($id) {
                return (int) $id;
            }, $ids);

            $this->setFoundPostsCache($query_hash, $ids);
        }

        $found_posts_ids = $ids;

        if ($orderby && 'rand' === $orderby) {
            if (empty($query->get('post__in'))) {
                shuffle($ids);

                if ($limit > 0) {
                    $ids = \array_slice($ids, 0, $limit);
                }

                $query->set('post__in', $ids);
            } else {
                shuffle($query->query_vars['post__in']);
            }

            $query->set('orderby', 'post__in');
        }

        $query->found_posts = \count($found_posts_ids);
        if (!empty($clauses['limits'])) {
            $query->max_num_pages = ceil($query->found_posts / $limit);
        }

        return $clauses;
    }

    /**
     * @param string $query_hash
     *
     * @return bool
     */
    private function invalidateFoundPostsCache(string $query_hash)
    {
        $global_invalidate_time = $this->getInvalidateTime();
        $local_invalidate_time = $this->getTimeFoundPosts($query_hash);

        if ($local_invalidate_time && $local_invalidate_time < $global_invalidate_time) {
            delete_transient($this->sanitizeKey("{$this->found_posts_key}-{$query_hash}"));
            delete_transient($this->sanitizeKey("time-{$this->found_posts_key}-{$query_hash}"));

            return true;
        }

        return false;
    }

    /**
     * @param string $query_hash
     *
     * @return bool|mixed
     */
    private function getFoundPostsCache($query_hash)
    {
        return get_transient($this->sanitizeKey("{$this->found_posts_key}-{$query_hash}"));
    }

    /**
     * @param string $query_hash
     * @param array  $ids
     */
    private function setFoundPostsCache(string $query_hash, array $ids)
    {
        set_transient($this->sanitizeKey("{$this->found_posts_key}-{$query_hash}"), $ids);
        set_transient($this->sanitizeKey("time-{$this->found_posts_key}-{$query_hash}"), time());
    }

    /**
     * @return bool|mixed
     */
    private function getInvalidateTime()
    {
        return get_transient($this->sanitizeKey($this->invalidate_time_key));
    }

    /**
     * @param string $query_hash
     *
     * @return bool|mixed
     */
    private function getTimeFoundPosts(string $query_hash)
    {
        return get_transient($this->sanitizeKey("time-{$this->found_posts_key}-{$query_hash}"));
    }

    /**
     * @param string $key
     *
     * @return string
     */
    private function sanitizeKey(string $key)
    {
        $key = "{$key}_{$this->cache_group}";

        return (string) sanitize_key($key);
    }

    /**
     * @param WP_Query $wp_query
     *
     * @return \WP_Query
     */
    public function setQueryParams(WP_Query $wp_query)
    {
        if ($wp_query->is_main_query()) {
            return $wp_query;
        }

        $wp_query->set('no_found_rows', true);
        $wp_query->set('showposts', null);
        $wp_query->set('posts_per_archive_page', null);
        $wp_query->set('ignore_sticky_posts', true);
    }

    /**
     * @param int $post_id
     */
    public function flushFoundRowsCach($post_id)
    {
        set_transient($this->sanitizeKey($this->invalidate_time_key), time());
    }
}
