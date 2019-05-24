<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;

/**
 * Class Divi.
 */
class Divi implements AutoloadInterface
{
    public function load()
    {
        add_action('init', [$this, 'removeActionsOnInit']);

        add_action('after_setup_theme', [$this, 'removeActionsAfterSetupTheme']);
        add_action('init', [$this, 'removeDiviFilter']);
        add_action('init', [$this, 'removeDiviImageSizes']);

        $this->removePluginsCompatibility();
    }

    public function removeActionsOnInit()
    {
        remove_action('pre_get_posts', 'et_builder_wc_pre_get_posts');
        remove_action('pre_get_posts', 'et_pb_custom_search');
        remove_action('pre_get_posts', 'et_custom_posts_per_page');

        if (!is_admin()) {
            remove_action('pre_get_posts', 'exclude_premade_layouts_library');
        }

        remove_action('wp_footer', 'integration_body', 12);
        remove_action('wp_head', 'et_add_custom_css', 100);
        remove_action('wp_head', 'add_favicon');
        remove_action('wp_head', 'head_addons', 7);
        remove_action('wp_head', 'integration_head', 12);

        remove_action('wp', 'et_fb_remove_emoji_detection_script');
        remove_action('wp', 'et_pb_ab_init');

        remove_action('wp_enqueue_scripts', 'et_add_responsive_shortcodes_css', 11);

        remove_action('init', 'et_create_images_temp_folder');
        remove_action('init', 'et_sync_custom_css_options');
        remove_action('et_after_post', 'integration_single_bottom', 12);
        remove_action('et_before_post', 'integration_single_top', 12);
        remove_action('update_option_upload_path', 'et_update_uploads_dir');
    }

    public function removeActionsAfterSetupTheme()
    {
        remove_action('wp_head', 'et_add_viewport_meta');
        remove_action('wp_head', 'et_maybe_add_scroll_to_anchor_fix', 9);
        remove_action('wp', 'et_divi_add_customizer_css');
        remove_action('wp_enqueue_scripts', 'et_divi_replace_stylesheet', 99999998);
    }

    public function removeDiviFilter()
    {
        add_filter('et_theme_image_sizes', '__return_false');

        remove_filter('template_include', 'et_builder_wc_template_include', 20);
        remove_filter('pre_get_document_title', 'elegant_titles_filter');

        remove_filter('wp_get_custom_css', 'et_epanel_handle_custom_css_output', 999);
        remove_filter('update_custom_css_data', 'et_update_custom_css_data_cb');
        remove_filter('update_custom_css_data', 'et_back_sync_custom_css_options');
        remove_filter('body_class', 'et_customizer_color_scheme_class');
        remove_filter('body_class', 'et_divi_theme_body_class');
        remove_filter('body_class', 'et_customizer_button_class');
        remove_filter('body_class', 'et_add_wp_version');
        remove_filter('body_class', 'et_divi_sidebar_class');
        remove_filter('body_class', 'et_divi_customize_preview_class');
        remove_filter('body_class', 'et_builder_body_classes');
        remove_filter('body_class', 'et_builder_wc_body_class');
        remove_filter('body_class', 'et_fb_add_body_class');

        add_filter('body_class', static function ($classes) {
            if (\function_exists('et_get_option')) {
                $post_id = get_the_ID();
                $page_custom_gutter = get_post_meta($post_id, '_et_pb_gutter_width', true);
                $gutter_width = !empty($page_custom_gutter) && is_singular() ? $page_custom_gutter : et_get_option('gutter_width', '3');

                $classes[] = get_post_meta($post_id, '_et_pb_custom_css_page_class', true);

                $classes[] = esc_attr("et_pb_gutters{$gutter_width}");
            }

            return $classes;
        });
    }

    public function removeDiviImageSizes()
    {
        global $et_theme_image_sizes;

        if (!empty($et_theme_image_sizes)) {
            $image_sizes = array_values($et_theme_image_sizes);
            foreach ($image_sizes as $image_size) {
                remove_image_size($image_size);
            }
        }
    }

    public function removePluginsCompatibility()
    {
        // Load plugin.php for frontend usage
        if (!\function_exists('is_plugin_active') || !\function_exists('get_plugins')) {
            require_once ABSPATH.'wp-admin/includes/plugin.php';
        }

        foreach (array_keys(get_plugins()) as $plugin) {
            // Load plugin compat file if plugin is active
            if (is_plugin_active($plugin)) {
                $plugin_compat_name = \dirname($plugin);
                add_filter("et_builder_plugin_compat_path_{$plugin_compat_name}", '__return_false');
            }
        }
    }
}
