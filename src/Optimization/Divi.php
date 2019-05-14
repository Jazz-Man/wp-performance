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
        add_action('init', [$this, 'removeDiviActions']);
        add_action('init', [$this, 'removeDiviFilter']);
        add_action('init', [$this, 'removeDiviImageSizes']);
        add_filter('et_module_shortcode_output', [$this, 'etModuleShortcodeOutput'], 10, 3);
    }

    public function removeDiviActions()
    {
        remove_action('wp', 'et_pb_ab_init');
        remove_action('wp', 'et_divi_add_customizer_css');
        remove_action('init', 'et_sync_custom_css_options');
        remove_action('wp_head', 'head_addons', 7);
        remove_action('wp_head', 'add_favicon');
        remove_action('wp_head', 'integration_head');
        remove_action('wp_head', 'et_add_viewport_meta');
        remove_action('wp_enqueue_scripts', 'et_divi_replace_stylesheet', 99999998);
    }

    public function removeDiviFilter()
    {
        add_filter('et_theme_image_sizes', '__return_false');
        remove_filter('body_class', 'et_layout_body_class');
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

    /**
     * @param string              $output
     * @param string              $render_slug
     * @param \ET_Builder_Element $element
     *
     * @return mixed
     */
    public function etModuleShortcodeOutput(string $output, string $render_slug, $element)
    {
        return apply_filters("et_module_{$render_slug}_shortcode_output", $output, $element);
    }
}
