<?php

namespace JazzMan\Performance\Utils;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Post\CustomPostType;
use WP_Post;

class WPBlocks implements AutoloadInterface {
    private string $postType;

    public function load(): void {
        $customPostType = new CustomPostType('wp_block');
        $customPostType->registerTaxonomy('block_category', [
            'public' => false,
            'show_ui' => true,
        ]);

        $this->postType = $customPostType->post_type;

        $customPostType->setColumns([
            'cb' => '<input type="checkbox" />',
            'title' => __('Title'),
            'post_id' => __('Block ID'),
            'post_name' => __('Block Slug'),
            'block_category' => __('Category'),
            'date' => __('Date'),
        ]);

        $customPostType->setPopulateColumns('post_name', static function ($column, WP_Post $post): void {
            printf('<code>%s</code>', esc_attr($post->post_name));
        });

        add_action('admin_menu', function (): void {
            $this->reusableBlocks();
        });
        add_action("save_post_$this->postType", function (int $postId, WP_Post $post): void {
            $this->resetWpBlockCache($postId, $post);
        }, 10, 2);
    }

    public function reusableBlocks(): void {
        $postTypeProps = [
            'post_type' => $this->postType,
        ];

        $pageTitle = 'Reusable Blocks';
        $taxTitle = 'Blocks Tax';
        $capability = 'edit_posts';

        $wpBlockSlug = add_query_arg($postTypeProps, 'edit.php');
        $wpBlockTaxSlug = add_query_arg(
            array_merge($postTypeProps, [
                'taxonomy' => 'block_category',
            ]),
            'edit-tags.php'
        );

        add_menu_page($pageTitle, $pageTitle, $capability, $wpBlockSlug, null, 'dashicons-editor-table', 22);

        add_submenu_page($wpBlockSlug, $taxTitle, $taxTitle, $capability, $wpBlockTaxSlug);
    }

    public function resetWpBlockCache(int $postId, WP_Post $wpPost): void {
        wp_cache_delete("{$wpPost->post_type}_$wpPost->post_name", Cache::CACHE_GROUP);
    }
}
