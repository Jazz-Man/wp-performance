<?php

namespace JazzMan\Performance\Utils;

use JazzMan\AutoloadInterface\AutoloadInterface;

class Cache implements AutoloadInterface
{
    public const CACHE_GROUP = 'wp-performance';
    public const QUERY_CACHE_GROUP = 'query';

    public function load()
    {
        add_action('delete_post', [$this, 'resetMenuCacheByMenuId']);
        add_action('delete_term', [$this, 'resetMenuCacheByTermId']);
        add_action('wp_update_nav_menu_item', [$this, 'resetMenuCacheByMenuId']);
        add_action('wp_add_nav_menu_item', [$this, 'resetMenuCacheByMenuId']);
        add_action('wp_create_nav_menu', [$this, 'resetMenuCacheByTermId']);
        add_action('saved_nav_menu', [$this, 'resetMenuCacheByTermId']);

        add_action('save_post_attachment', [$this, 'resetAttachmentCache'], 10, 2);
        add_action('saved_term', [$this, 'termsCache'], 10, 3);
    }

    public function resetAttachmentCache(int $post_id, \WP_Post $post)
    {
        wp_cache_delete("attachment_image_{$post_id}", self::CACHE_GROUP);
    }

    public function termsCache(int $term_id, int $tt_id, string $taxonomy)
    {
        wp_cache_delete("taxonomy_ancestors_{$term_id}_{$taxonomy}", self::CACHE_GROUP);
        wp_cache_delete("term_all_children_{$term_id}", self::CACHE_GROUP);

        app_term_get_all_children($term_id);
    }

    public function resetMenuCacheByTermId(int $term_id): void
    {
        /** @var \WP_Term $term */
        $term = get_term($term_id, 'nav_menu');

        if (!empty($term) && !is_wp_error($term)) {
            self::deleteMenuItemCache($term);
        }
    }

    public function resetMenuCacheByMenuId(int $menuId): void
    {
        /** @var \WP_Term[] $terms */
        $terms = wp_get_post_terms($menuId, 'nav_menu');

        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                self::deleteMenuItemCache($term);
            }
        }
    }

    private static function deleteMenuItemCache(\WP_Term $item): void
    {
        wp_cache_delete(Cache::getMenuItemCacheKey($item), 'menu_items');
    }

    /**
     * @param  \WP_Term  $item
     *
     * @return string
     */
    public static function getMenuItemCacheKey(\WP_Term $item): string
    {
        return "{$item->taxonomy}_{$item->slug}";
    }
}
