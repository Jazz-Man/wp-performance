<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\App;

/**
 * Class Enqueue.
 */
class Enqueue implements AutoloadInterface
{
    public function load()
    {
        add_filter('app_preload_links', [$this, 'preload_links']);
        add_action('wp_enqueue_scripts', [$this, 'js_to_footer']);
        add_action('wp_enqueue_scripts', [$this, 'jquery_from_cdn']);

        if (! is_admin()) {
            add_filter('script_loader_src', [$this, 'add_script_version'], 15, 2);
            add_filter('style_loader_src', [$this, 'add_script_version'], 15, 2);
        }
    }

    public function preload_links(array $links): array
    {
        $links[] = Http::get_dns_prefetch_link('https://code.jquery.com');

        return $links;
    }

    /**
     * @param  string  $src
     * @param  string  $handle
     *
     * @return string
     */
    public function add_script_version(string $src, string $handle)
    {
        $is_current_host = Http::is_current_host($src);

        if (\filter_var($src, FILTER_VALIDATE_URL)) {
            $add_script_version = (bool) apply_filters('enqueue_add_script_version', true, $handle);

            if ($add_script_version) {
                if ($is_current_host) {
                    $path = \parse_url($src, PHP_URL_PATH);
                    $path = \ltrim($path, '/');
                    $root = App::getRootDir();

                    if (is_multisite() && ($blog = get_blog_details(null, false))) {
                        $path = \ltrim($path, $blog->path);
                    }

                    $file = "{$root}/{$path}";

                    if (\is_file($file)) {
                        $timestamp = \is_file("{$file}.map") ? \filemtime("{$file}.map") : \filemtime($file);

                        $src = add_query_arg([
                            'ver' => $timestamp,
                        ], $src);

                        return esc_url($src);
                    }
                }
            } elseif (\strpos($src, '?ver=')) {
                $src = remove_query_arg('ver', $src);
            }
        }

        if ($is_current_host) {
            return wp_make_link_relative($src);
        }

        return $src;
    }

    public static function jquery_from_cdn()
    {
        $registered_scripts = wp_scripts()->registered;

        /** @var \_WP_Dependency $jquery_core */
        $jquery_core = $registered_scripts['jquery-core'];

        $jquery_ver = \trim($jquery_core->ver, '-wp');

        self::deregister_script($jquery_core->handle, "https://code.jquery.com/jquery-{$jquery_ver}.min.js");
        self::deregister_script('jquery');

        wp_register_script('jquery', false, [$jquery_core->handle], $jquery_ver, true);
    }

    public function js_to_footer()
    {
        remove_action('wp_head', 'wp_print_scripts');
        remove_action('wp_head', 'wp_print_head_scripts', 9);
        remove_action('wp_head', 'wp_enqueue_scripts', 1);
    }

    /**
     * @param null $new_url
     */
    public static function deregister_script(string $handle, $new_url = null)
    {
        $registered_scripts = wp_scripts()->registered;

        if (! empty($registered_scripts[$handle])) {
            /** @var \_WP_Dependency $js_lib */
            $js_lib = $registered_scripts[$handle];

            wp_dequeue_script($js_lib->handle);
            wp_deregister_script($js_lib->handle);

            if (! empty($new_url)) {
                wp_register_script($js_lib->handle, $new_url, $js_lib->deps, $js_lib->ver, true);
            }
        }
    }

    /**
     * @param  string  $handle
     * @param  string|bool|null  $new_url
     */
    public static function deregister_style(string $handle, string $new_url = null)
    {
        $registered_style = wp_styles()->registered;

        if (! empty($registered_style[$handle])) {
            /** @var \_WP_Dependency $css_lib */
            $css_lib = $registered_style[$handle];

            wp_dequeue_style($css_lib->handle);
            wp_deregister_style($css_lib->handle);

            if (! empty($new_url)) {
                wp_register_style($css_lib->handle, $new_url, $css_lib->deps, $css_lib->ver, $css_lib->args);
            }
        }
    }
}
