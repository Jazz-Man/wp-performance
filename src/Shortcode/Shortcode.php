<?php

namespace JazzMan\Performance\Shortcode;

use JazzMan\AutoloadInterface\AutoloadInterface;

/**
 * Class Shortcode.
 *
 * @see https://github.com/gschoppe/Better-Shortcode-Parser
 */
class Shortcode implements AutoloadInterface
{
    public function load()
    {
        add_action('init', [$this, 'init']);
    }

    public function init()
    {
        if (\defined('ET_CORE_VERSION')) {
            add_filter('shortcode_disable_nested_rendering', '__return_true');
        }

        remove_filter('the_content', 'do_shortcode', 11);
        remove_filter('widget_text_content', 'do_shortcode', 11);
        add_filter('the_content', [$this, 'do_shortcode'], 11);
        add_filter('widget_text_content', [$this, 'do_shortcode'], 11);
    }

    /**
     * @param      $content
     * @param bool $ignore_html
     *
     * @return mixed
     */
    public function do_shortcode($content, $ignore_html = false)
    {
        return app_do_shortcode($content);
    }
}
