<?php

namespace JazzMan\Performance\Utils;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Post\CustomPostType;
use WP_Post;

class WPBlocks implements AutoloadInterface
{
    /**
     * @var string
     */
    private string $postType;

    /**
     * @return void
     */
    public function load()
    {
        $wpBlock = new CustomPostType('wp_block');
        $wpBlock->registerTaxonomy('block_category', [
            'public' => false,
            'show_ui' => true,
        ]);

        $this->postType = $wpBlock->post_type;

        $wpBlock->setColumns([
            'cb' => '<input type="checkbox" />',
            'title' => __('Title'),
            'post_id' => __('Block ID'),
            'post_name' => __('Block Slug'),
            'block_category' => __('Category'),
            'date' => __('Date'),
        ]);

        $wpBlock->setPopulateColumns('post_name', static function ($column, WP_Post $post) {
            printf('<code>%s</code>', esc_attr($post->post_name));
        });

        add_action('admin_menu', [$this, 'reusableBlocks']);
        add_action("save_post_$this->postType", [$this, 'resetWpBlockCache'], 10, 2);
    }

    /**
     * @return void
     */
    public function reusableBlocks()
    {
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

    /**
     * @param  int  $postId
     * @param  WP_Post  $post
     * @return void
     */
    public function resetWpBlockCache(int $postId, WP_Post $post)
    {
        wp_cache_delete("{$post->post_type}_$post->post_name", Cache::CACHE_GROUP);
    }
}
