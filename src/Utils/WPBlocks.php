<?php

namespace JazzMan\Performance\Utils;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Post\CustomPostType;
use WP_Post;

class WPBlocks implements AutoloadInterface {
    private ?string $postType = null;

    public function load(): void {
        $customPostType = new CustomPostType('wp_block');
        $customPostType->registerTaxonomy('block_category', [
            'public' => false,
            'show_ui' => true,
        ]);

        $this->postType = $customPostType->post_type;

        $customPostType->setColumns([
            'post_id' => __('Block ID'),
            'post_name' => __('Block Slug'),
        ]);

        $customPostType->setPopulateColumns('post_name', static function (string $column, WP_Post $post): void {
            printf('<code>%s</code>', esc_attr($post->post_name));
        });

        add_action('admin_menu', [$this, 'reusableBlocks']);
        add_action(sprintf('save_post_%s', $this->postType), [$this, 'resetWpBlockCache'], 10, 2);
    }

    public function reusableBlocks(): void {
        $postTypeProps = [
            'post_type' => $this->postType,
        ];

        $wpBlockSlug = add_query_arg($postTypeProps, 'edit.php');

        add_menu_page(
            'Reusable Blocks',
            'Reusable Blocks',
            'edit_posts',
            $wpBlockSlug,
            '',
            'dashicons-editor-table',
            22
        );

        $postTypeProps['taxonomy'] = 'block_category';

        add_submenu_page(
            $wpBlockSlug,
            'Blocks Tax',
            'Blocks Tax',
            'edit_posts',
            add_query_arg(
                $postTypeProps,
                'edit-tags.php'
            )
        );
    }

    public function resetWpBlockCache(int $postId, WP_Post $wpPost): void {
        wp_cache_delete(sprintf('%s_%s', $wpPost->post_type, $wpPost->post_name), Cache::CACHE_GROUP);
    }
}
