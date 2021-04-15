<?php

namespace JazzMan\Performance\Utils;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Post\CustomPostType;

class WPBlocks implements AutoloadInterface
{
    /**
     * @var string
     */
    private $post_type;

    public function load()
    {
        $wp_block = new CustomPostType('wp_block');
        $wp_block->registerTaxonomy('block_category', [
            'public' => false,
            'show_ui' => true,
        ]);

        $this->post_type = $wp_block->post_type;

        $wp_block->setColumns([
            'cb' => '<input type="checkbox" />',
            'title' => __('Title'),
            'post_id' => __('Block ID'),
            'post_name' => __('Block Slug'),
            'block_category' => __('Category'),
            'date' => __('Date'),
        ]);

        $wp_block->setPopulateColumns('post_name', static function ($column, \WP_Post $post) {
            \printf('<code>%s</code>', esc_attr($post->{$column}));
        });

        add_action('admin_menu', [$this, 'reusableBlocks']);
    }

    public function reusableBlocks()
    {
        $post_type_props = [
            'post_type' => $this->post_type,
        ];

        $page_title = 'Reusable Blocks';
        $tax_title = 'Blocks Tax';
        $capability = 'edit_posts';

        $wp_block_slug = add_query_arg($post_type_props, 'edit.php');
        $wp_block_tax_slug = add_query_arg(\array_merge($post_type_props, [
            'taxonomy' => 'block_category',
        ]), 'edit-tags.php');

        add_menu_page($page_title, $page_title, $capability, $wp_block_slug, '', 'dashicons-editor-table', 22);

        add_submenu_page($wp_block_slug, $tax_title, $tax_title, $capability, $wp_block_tax_slug);
    }
}
