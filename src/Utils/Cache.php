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
        wp_cache_delete(sprintf('attachment_image_%d', $postId), self::CACHE_GROUP);
    }

    public function termsCache(int $termId, int $termTaxId, string $taxonomy): void {
        wp_cache_delete(sprintf('taxonomy_ancestors_%d_%s', $termId, $taxonomy), self::CACHE_GROUP);
        wp_cache_delete(sprintf('term_all_children_%d', $termId), self::CACHE_GROUP);

        app_term_get_all_children($termId);
    }

    public function resetMenuCacheByTermId(int $termId): void {
        $term = get_term($termId, 'nav_menu');

        if ( $term instanceof WP_Term) {
            self::deleteMenuItemCache($term);
        }
    }

    public function resetMenuCacheByMenuId(int $menuId): void {
        $terms = wp_get_post_terms($menuId, 'nav_menu');

        if ( ! ($terms instanceof WP_Error)) {
            foreach ($terms as $term) {
                self::deleteMenuItemCache($term);
            }
        }
    }

    private static function deleteMenuItemCache(WP_Term $wpTerm): void {
        wp_cache_delete(self::getMenuItemCacheKey($wpTerm), 'menu_items');
    }

    public static function getMenuItemCacheKey(WP_Term $wpTerm): string {
        return sprintf('%s_%s', $wpTerm->taxonomy, $wpTerm->slug);
    }
}
