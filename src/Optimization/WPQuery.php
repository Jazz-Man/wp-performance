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
    /**
     * @var string
     */
    private $query_hash;
    /**
     * @var bool
     */
    private $rand = false;

    public function load()
    {
        add_action('save_post', [$this, 'flush_found_rows_cach']);

        add_filter('pre_get_posts', [$this, 'set_query_params'], 10, 1);
        add_filter('posts_clauses_request', [$this, 'posts_clauses_request'], 10, 2);

        add_filter('woocommerce_install_skip_create_files', '__return_true');
    }

    /**
     * @param array     $clauses
     *
     * @param \WP_Query $wp_query
     *
     * @return array
     */
    public function posts_clauses_request($clauses, WP_Query $wp_query): array
    {
        if ($wp_query->is_main_query()) {
            return $clauses;
        }

        $limit = $wp_query->get('posts_per_page');

        $this->invalidate_found_posts_cache();
        $ids = $this->get_found_posts_cache();

        if (false === $ids) {
            global $wpdb;

            $pdo = app_db_pdo();

            $where = $clauses['where'] ?? '';
            $join = $clauses['join'] ?? '';

            $ids_st = $pdo->prepare("SELECT {$wpdb->posts}.ID FROM {$wpdb->posts} {$join} WHERE 1=1 {$where} GROUP BY {$wpdb->posts}.ID ORDER BY {$wpdb->posts}.post_date");

            $ids_st->execute();

            $ids = $ids_st->fetchAll(\PDO::FETCH_COLUMN);

            $this->set_found_posts_cache($ids);
        }

        $wp_query->found_posts = \count($ids);
        if (! empty($clauses['limits'])) {
            $wp_query->max_num_pages = \ceil($wp_query->found_posts / $limit);
        }

        return $clauses;
    }

    /**
     * @param  \WP_Query  $wp_query
     *
     * @return \WP_Query|void
     */
    public function set_query_params(WP_Query $wp_query)
    {
        if ($wp_query->is_main_query()) {
            return $wp_query;
        }

        $limit = $wp_query->get('posts_per_page');

        $orderby = $wp_query->get('orderby', false);
        $this->rand = (isset($orderby) && 'rand' === $orderby);


        $wp_query->set('no_found_rows', true);
        $wp_query->set('showposts', null);
        $wp_query->set('posts_per_archive_page', null);
        $wp_query->set('ignore_sticky_posts', true);

        $this->query_hash = \md5(\serialize($wp_query->query_vars));

        if ($this->rand){

            $ids = $this->get_found_posts_cache();

            if ($ids){
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

    /**
     * Parse an 'order' query variable and cast it to ASC or DESC as necessary.
     *
     * @since 4.0.0
     *
     * @param string $order The 'order' query variable.
     * @return string The sanitized 'order' query variable.
     */
    protected function parse_order($order): string
    {
        if (! \is_string($order) || empty($order)) {
            return 'DESC';
        }

        if ('ASC' === \strtoupper($order)) {
            return 'ASC';
        }

        return 'DESC';
    }

    /**
     * @param int $post_id
     * @return void
     */
    public function flush_found_rows_cach($post_id)
    {
        wp_cache_set($this->invalidate_time_key, \time(), $this->cache_group);
    }

    /**
     * @return bool
     */
    private function invalidate_found_posts_cache()
    {
        $global_invalidate_time = $this->get_invalidate_time();
        $local_invalidate_time = $this->get_time_found_posts();

        if ($local_invalidate_time && $local_invalidate_time < $global_invalidate_time) {
            wp_cache_delete("{$this->found_posts_key}-{$this->query_hash}", $this->cache_group);
            wp_cache_delete("time-{$this->found_posts_key}-{$this->query_hash}", $this->cache_group);

            return true;
        }

        return false;
    }

    /**
     * @return bool|mixed
     */
    private function get_found_posts_cache()
    {
        return wp_cache_get("{$this->found_posts_key}-{$this->query_hash}", $this->cache_group);
    }

    /**
     * @param  array  $ids
     *
     * @return void
     */
    private function set_found_posts_cache(array $ids)
    {
        wp_cache_set("{$this->found_posts_key}-{$this->query_hash}", $ids, $this->cache_group);
        wp_cache_set("time-{$this->found_posts_key}-{$this->query_hash}", \time(), $this->cache_group);
    }

    /**
     * @return bool|mixed
     */
    private function get_invalidate_time()
    {
        return wp_cache_get($this->invalidate_time_key, $this->cache_group);
    }

    /**
     * @return bool|mixed
     */
    private function get_time_found_posts()
    {
        return wp_cache_get("time-{$this->found_posts_key}-{$this->query_hash}", $this->cache_group);
    }
}
