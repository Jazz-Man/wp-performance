<?php

namespace JazzMan\Performance\Utils;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_Term;

class Cache implements AutoloadInterface {
    public const CACHE_GROUP = 'wp-performance';

    public const QUERY_CACHE_GROUP = 'query';

    public function load(): void {
        add_action('delete_post', function (int $menuId): void {
            $this->resetMenuCacheByMenuId($menuId);
        });
        add_action('delete_term', function (int $termId): void {
            $this->resetMenuCacheByTermId($termId);
        });
        add_action('wp_update_nav_menu_item', function (int $menuId): void {
            $this->resetMenuCacheByMenuId($menuId);
        });
        add_action('wp_add_nav_menu_item', function (int $menuId): void {
            $this->resetMenuCacheByMenuId($menuId);
        });
        add_action('wp_create_nav_menu', function (int $termId): void {
            $this->resetMenuCacheByTermId($termId);
        });
        add_action('saved_nav_menu', function (int $termId): void {
            $this->resetMenuCacheByTermId($termId);
        });

        add_action('save_post_attachment', function (int $postId): void {
            $this->resetAttachmentCache($postId);
        });
        add_action('saved_term', function (int $termId, int $termTaxId, string $taxonomy): void {
            $this->termsCache($termId, $termTaxId, $taxonomy);
        }, 10, 3);
    }

    public function resetAttachmentCache(int $postId): void {
        wp_cache_delete("attachment_image_$postId", self::CACHE_GROUP);
    }

    public function termsCache(int $termId, int $termTaxId, string $taxonomy): void {
        wp_cache_delete("taxonomy_ancestors_{$termId}_$taxonomy", self::CACHE_GROUP);
        wp_cache_delete("term_all_children_$termId", self::CACHE_GROUP);

        app_term_get_all_children($termId);
    }

    public function resetMenuCacheByTermId(int $termId): void {
        /** @var WP_Term $term */
        $term = get_term($termId, 'nav_menu');

        if ( $term instanceof WP_Term) {
            self::deleteMenuItemCache($term);
        }
    }

    public function resetMenuCacheByMenuId(int $menuId): void {
        /** @var WP_Term[] $terms */
        $terms = wp_get_post_terms($menuId, 'nav_menu');

        if ( ! is_wp_error($terms)) {
            foreach ($terms as $term) {
                self::deleteMenuItemCache($term);
            }
        }
    }

    private static function deleteMenuItemCache(WP_Term $wpTerm): void {
        wp_cache_delete(self::getMenuItemCacheKey($wpTerm), 'menu_items');
    }

    public static function getMenuItemCacheKey(WP_Term $wpTerm): string {
        return "{$wpTerm->taxonomy}_$wpTerm->slug";
    }
}
