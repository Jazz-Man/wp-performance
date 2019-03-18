<?php

namespace JazzMan\Performance;

use WP_Query;

/**
 * Class WP_Query.
 */
class WP_Query_Performance implements AutoloadInterface
{

    /**
     * @var string
     */
    private $cache_group = 'query';

    public function load()
    {
        add_action('pre_get_posts', [$this, 'order_by_rand_optimization']);
        add_action('save_post', [$this, 'save_post_flush_cache']);

        add_filter('posts_orderby', [$this, 'sql_calc_found_rows_optimization'], 10, 2);
        add_filter('found_posts', [$this, 'sql_calc_found_rows_caching'], 99, 2);

        add_filter('pre_get_posts', [$this, 'set_no_found_rows'], 10, 1);
        add_filter('wp_link_query_args', [$this, 'set_no_found_rows'], 10, 1);

        add_filter('posts_clauses', [$this, 'wpartisan_set_found_posts'], 10, 2);
        add_filter( 'woocommerce_install_skip_create_files', '__return_true' );
    }

    /**
     * @param WP_Query $query
     */
    public function order_by_rand_optimization(WP_Query $query)
    {
        $orderby = $query->get('orderby', false);

        if ($orderby && 'rand' === $orderby) {
            $query_hash = $query->query_vars_hash;
            if (empty($query->query_vars['post__in'])) {
                $global_invalidate_time = $this->get_invalidate_time();
                $local_invalidate_time  = $this->get_time_random_posts($query_hash);
                if ($local_invalidate_time && $local_invalidate_time < $global_invalidate_time) {
                    $this->delete_random_posts($query_hash);
                    $this->delete_time_random_posts($query_hash);
                }
                if (false === ($ids = $this->get_random_posts($query_hash))) {
                    $vars                           = $query->query_vars;
                    $vars['orderby']                = 'date';
                    $vars['order']                  = 'DESC';
                    $vars['posts_per_page']         = 1000; // do you really need more?
                    $vars['showposts']              = null;
                    $vars['posts_per_archive_page'] = null;
                    $vars['fields']                 = 'ids';
                    $vars['no_found_rows']          = true;
                    $vars['ignore_sticky_posts']    = true;
                    $ids                            = get_posts($vars);
                    $this->set_random_posts($query_hash, $ids);
                    $this->set_time_random_posts($query_hash);
                }
                shuffle($ids);

                $limit = empty($query->query_vars['posts_per_page']) ? get_option('posts_per_page') : $query->query_vars['posts_per_page'];
                if ($limit > 0) {
                    $ids = \array_slice($ids, 0, $limit);
                }
                $query->query_vars['post__in'] = $ids;
            } else {
                shuffle($query->query_vars['post__in']);
            }
            $query->query_vars['orderby']       = 'post__in';
            $query->query_vars['no_found_rows'] = true;
            $query->found_posts                 = \count($query->query_vars['post__in']);
        }
    }

    /**
     * @param string   $orderby
     * @param WP_Query $query
     *
     * @return string
     */
    public function sql_calc_found_rows_optimization($orderby, WP_Query $query)
    {
        if ($query->get('no_found_rows')) {
            $query_hash = $query->query_vars_hash;

            $global_invalidate_time = $this->get_invalidate_time();
            $local_invalidate_time  = $this->get_time_found_posts($query_hash);

            if ($local_invalidate_time && $local_invalidate_time < $global_invalidate_time) {
                $this->delete_cached_found_posts($query_hash);
                $this->delete_time_found_posts($query_hash);

                return $orderby;
            }
            if (false !== ($found_posts = $this->get_cached_found_posts($query_hash))) {
                $query->found_posts                 = $found_posts;
                $query->max_num_pages               = ceil($query->found_posts / $query->query_vars['posts_per_page']);
                $query->query_vars['no_found_rows'] = true;
            }
        }

        return $orderby;
    }


    /**
     * Improve perfomance of the `_WP_Editors::wp_link_query` method
     * The WordPress core is currently not setting `no_found_rows` inside the `_WP_Editors::wp_link_query`
     *
     * @see  https://core.trac.wordpress.org/ticket/38784
     * Since the `_WP_Editors::wp_link_query` method is not using the `found_posts` nor `max_num_pages` properties of
     * `WP_Query` class, the `SQL_CALC_FOUND_ROWS` in produced SQL query is extra and useless.
     *
     * @param array $query
     *
     * @return array
     */
    public function wp_link_query_args($query)
    {
        $query['no_found_rows'] = true;

        return $query;
    }

    /**
     * @param WP_Query $wp_query
     */
    public function set_no_found_rows(WP_Query $wp_query)
    {
        $wp_query->set('no_found_rows', true);
    }

    /**
     * @param array    $clauses
     * @param WP_Query $wp_query
     *
     * @return array
     */
    public function wpartisan_set_found_posts($clauses, WP_Query $wp_query)
    {
        // Don't proceed if it's a singular page.
        if ($wp_query->is_singular()) {
            return $clauses;
        }

        $found_posts = $this->get_cached_found_posts($wp_query->query_vars_hash);

        if (false === $found_posts) {
            global $wpdb;

            // Check if they're set.
            $where    = $clauses['where'] ?? '';
            $join     = $clauses['join'] ?? '';
            $distinct = $clauses['distinct'] ?? '';

            $found_posts = $wpdb->get_var("SELECT $distinct COUNT(*) FROM {$wpdb->posts} $join WHERE 1=1 $where");
        }

        // Construct and run the query. Set the result as the 'found_posts'
        // param on the main query we want to run.
        $wp_query->found_posts = $found_posts;

        // Work out how many posts per page there should be.
        $posts_per_page = (! empty($wp_query->query_vars['posts_per_page']) ? absint($wp_query->query_vars['posts_per_page']) : absint(get_option('posts_per_page')));

        // Set the max_num_pages.
        $wp_query->max_num_pages = ceil($wp_query->found_posts / $posts_per_page);

        // Return the $clauses so the main query can run.
        return $clauses;
    }

    /**
     * @param int      $found_posts
     * @param WP_Query $query
     *
     * @return int
     */
    public function sql_calc_found_rows_caching($found_posts, WP_Query $query)
    {
        $query_hash = $query->query_vars_hash;

        wp_cache_set("found-posts-{$query_hash}", $found_posts, 'query');
        wp_cache_set("time-found-posts-{$query_hash}", time(), 'query');

        return $found_posts;
    }

    /**
     * @param int $post_id
     */
    public function save_post_flush_cache($post_id)
    {
        wp_cache_set('invalidate-time', time(), $this->cache_group);
    }

    /**
     * @param string $query_hash
     *
     * @return bool|mixed
     */
    private function get_random_posts($query_hash)
    {
        return wp_cache_get("random-posts-{$query_hash}", $this->cache_group);
    }

    /**
     * @param string $query_hash
     * @param mixed  $data
     *
     * @return bool
     */
    private function set_random_posts($query_hash, $data)
    {
        return wp_cache_set("random-posts-{$query_hash}", $data, $this->cache_group);
    }

    /**
     * @param string $query_hash
     *
     * @return bool
     */
    private function delete_random_posts($query_hash)
    {
        return wp_cache_delete("random-posts-{$query_hash}", $this->cache_group);
    }

    /**
     * @param string $query_hash
     *
     * @return bool|mixed
     */
    private function get_time_random_posts($query_hash)
    {
        return wp_cache_get("time-random-posts-{$query_hash}", $this->cache_group);
    }

    /**
     * @param string $query_hash
     *
     * @return bool|mixed
     */
    private function set_time_random_posts($query_hash)
    {
        return wp_cache_set("time-random-posts-{$query_hash}", time(), $this->cache_group);
    }

    /**
     * @param string $query_hash
     *
     * @return bool
     */
    private function delete_time_random_posts($query_hash)
    {
        return wp_cache_delete("time-random-posts-{$query_hash}", $this->cache_group);
    }

    /**
     * @param string $query_hash
     *
     * @return bool|mixed
     */
    private function get_cached_found_posts($query_hash)
    {
        return wp_cache_get("found-posts-{$query_hash}", $this->cache_group);
    }

    /**
     * @param string $query_hash
     */
    private function delete_cached_found_posts($query_hash)
    {
        wp_cache_delete("found-posts-{$query_hash}", $this->cache_group);
    }

    /**
     * @param string $query_hash
     *
     * @return bool|mixed
     */
    private function get_time_found_posts($query_hash)
    {
        return wp_cache_get("time-found-posts-{$query_hash}", $this->cache_group);
    }

    /**
     * @param string $query_hash
     */
    private function delete_time_found_posts($query_hash)
    {
        wp_cache_delete("time-found-posts-{$query_hash}", $this->cache_group);
    }

    /**
     * @return bool|mixed
     */
    private function get_invalidate_time()
    {
        return wp_cache_get('invalidate-time', $this->cache_group);
    }
}
