<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_Query;

/**
 * Class WP_Query.
 */
class WPQuery implements AutoloadInterface
{
    public function load()
    {
        add_action('pre_get_posts', [$this, 'setQueryParams']);
        add_action('posts_clauses_request', [$this, 'orderByRandOptimization'], 10, 2);
    }

    /**
     * @param array    $pieces
     * @param WP_Query $query
     *
     * @return array
     */
    public function orderByRandOptimization($pieces, WP_Query $query)
    {
        global $wpdb;
        $orderby = $query->get('orderby', false);

        if ($orderby && 'rand' === $orderby) {
            $post_type = $query->get('post_type');
            $post_status = $query->get('post_status');

            $join_where = '';
            $join_where_array = [];

            if (!empty($post_type)) {
                $join_where_array[] = "{$wpdb->posts}.post_type IN ('".implode("', '",
                        (array) esc_sql($post_type))."')";
            }

            if (!empty($post_status)) {
                $join_where_array[] = "{$wpdb->posts}.post_status IN ('".implode("', '",
                        (array) esc_sql($post_status))."')";
            }

            if (!empty($join_where_array)) {
                $join_where = ' AND ';
                $join_where .= implode(' AND ', $join_where_array);
            }

            $pieces['join'] .= " JOIN (SELECT RAND() * (SELECT MAX({$wpdb->posts}.ID) FROM {$wpdb->posts} WHERE 1=1 {$join_where}) AS max_id) AS max_rand";
            $pieces['where'] .= " AND {$wpdb->posts}.ID >= max_rand.max_id";
            $pieces['orderby'] = "{$wpdb->posts}.ID ASC";
        }

        return $pieces;
    }

    /**
     * @param \WP_Query $query
     */
    public function setQueryParams(WP_Query $query)
    {
        $query->set('showposts', null);
        $query->set('posts_per_archive_page', null);
        $query->set('ignore_sticky_posts', true);
    }
}
