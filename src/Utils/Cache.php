<?php

namespace JazzMan\Performance\Utils;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_Term;

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

        add_action('save_post_attachment', [$this, 'resetAttachmentCache']);
        add_action('saved_term', [$this, 'termsCache'], 10, 3);
    }

    /**
     * @param  int  $postId
     * @return void
     */
    public function resetAttachmentCache(int $postId): void
    {
        wp_cache_delete("attachment_image_$postId", self::CACHE_GROUP);
    }

    /**
     * @param  int  $termId
     * @param  int  $termTaxId
     * @param  string  $taxonomy
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @return void
     *
     */
    public function termsCache(int $termId, int $termTaxId, string $taxonomy): void
    {
        wp_cache_delete("taxonomy_ancestors_{$termId}_$taxonomy", self::CACHE_GROUP);
        wp_cache_delete("term_all_children_$termId", self::CACHE_GROUP);

        app_term_get_all_children($termId);
    }

    public function resetMenuCacheByTermId(int $termId): void
    {
        /** @var \WP_Term $term */
        $term = get_term($termId, 'nav_menu');

        if ( $term instanceof WP_Term) {
            self::deleteMenuItemCache($term);
        }
    }

    public function resetMenuCacheByMenuId(int $menuId): void
    {
        /** @var WP_Term[] $terms */
        $terms = wp_get_post_terms($menuId, 'nav_menu');

        if ( ! is_wp_error($terms)) {
            foreach ($terms as $term) {
                self::deleteMenuItemCache($term);
            }
        }
    }

    private static function deleteMenuItemCache(WP_Term $item): void
    {
        wp_cache_delete(Cache::getMenuItemCacheKey($item), 'menu_items');
    }

    public static function getMenuItemCacheKey(WP_Term $item): string
    {
        return "{$item->taxonomy}_$item->slug";
    }
}
