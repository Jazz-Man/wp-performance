<?php

namespace JazzMan\Performance\Utils;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_Error;
use WP_Term;

class Cache implements AutoloadInterface {
    /**
     * @var string
     */
    public const CACHE_GROUP = 'wp-performance';

    /**
     * @var string
     */
    public const QUERY_CACHE_GROUP = 'query';

    public function load(): void {
        add_action('delete_post', [__CLASS__, 'resetMenuCacheByMenuId']);
        add_action('wp_update_nav_menu_item', [__CLASS__, 'resetMenuCacheByMenuId']);
        add_action('wp_add_nav_menu_item', [__CLASS__, 'resetMenuCacheByMenuId']);

        add_action('delete_term', [__CLASS__, 'resetMenuCacheByTermId']);
        add_action('wp_create_nav_menu', [__CLASS__, 'resetMenuCacheByTermId']);
        add_action('saved_nav_menu', [__CLASS__, 'resetMenuCacheByTermId']);

        add_action('save_post_attachment', [__CLASS__, 'resetAttachmentCache']);
        add_action('saved_term', [__CLASS__, 'termsCache'], 10, 3);
    }

    public static function resetAttachmentCache(int $postId): void {
        wp_cache_delete(sprintf('attachment_image_%d', $postId), self::CACHE_GROUP);
    }

    public static function termsCache(int $termId, int $termTaxId, string $taxonomy): void {
        wp_cache_delete(sprintf('taxonomy_ancestors_%d_%s', $termId, $taxonomy), self::CACHE_GROUP);
        wp_cache_delete(sprintf('term_all_children_%d', $termId), self::CACHE_GROUP);

        app_term_get_all_children($termId);
    }

    public static function resetMenuCacheByTermId(int $termId): void {
        /** @var WP_Error|WP_Term $term */
        $term = get_term($termId, 'nav_menu');

        if ($term instanceof WP_Term) {
            self::deleteMenuItemCache($term);
        }
    }

    public static function resetMenuCacheByMenuId(int $menuId): void {
        /** @var WP_Error|WP_Term[] $terms */
        $terms = wp_get_post_terms($menuId, 'nav_menu');

        if (!($terms instanceof WP_Error)) {
            foreach ($terms as $term) {
                self::deleteMenuItemCache($term);
            }
        }
    }

    public static function getMenuItemCacheKey(WP_Term $wpTerm): string {
        return sprintf('%s_%s', $wpTerm->taxonomy, $wpTerm->slug);
    }

    private static function deleteMenuItemCache(WP_Term $wpTerm): void {
        wp_cache_delete(self::getMenuItemCacheKey($wpTerm), 'menu_items');
    }
}
