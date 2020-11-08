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
        add_filter('app_preload_links', [$this, 'preloadLinks']);
        add_action('wp_enqueue_scripts', [$this, 'jsToFooter']);
        add_action('wp_enqueue_scripts', [$this, 'jqueryFromCdn']);

        if (! is_admin()) {
            add_filter('script_loader_src', [$this, 'addScriptVersion'], 15, 2);
            add_filter('style_loader_src', [$this, 'addScriptVersion'], 15, 2);
        }
    }

    /**
     * @param array $links
     * @return array
     */
    public function preloadLinks(array $links)
    {
        $dns_prefetch = [
            'https://code.jquery.com',
        ];

        foreach ($dns_prefetch as $url) {
            $links[] = Http::getDnsPrefetchLink($url);
            $links[] = Http::getPreconnectLink($url);
        }

        return $links;
    }

    /**
     * @param string $src
     * @param string $handle
     *
     * @return string
     */
    public function addScriptVersion($src, $handle)
    {
        if (! empty($src)) {
            $add_script_version = (bool) apply_filters('enqueue_add_script_version', true, $handle);

            if ($add_script_version) {
                $current_host = (string) $_SERVER['HTTP_HOST'];
                $url_host = \parse_url($src, PHP_URL_HOST);

                if (! empty($url_host) && $current_host === $url_host) {
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

        return $src;
    }

    public static function jqueryFromCdn()
    {
        $registered_scripts = wp_scripts()->registered;

        /** @var \_WP_Dependency $jquery_core */
        $jquery_core = $registered_scripts['jquery-core'];

        $jquery_ver = \trim($jquery_core->ver, '-wp');

        self::deregisterScript($jquery_core->handle, 'https://code.jquery.com/jquery-2.2.4.min.js');
        self::deregisterScript('jquery');

        wp_register_script('jquery', false, [$jquery_core->handle], $jquery_ver, true);
    }

    public function jsToFooter()
    {
        remove_action('wp_head', 'wp_print_scripts');
        remove_action('wp_head', 'wp_print_head_scripts', 9);
        remove_action('wp_head', 'wp_enqueue_scripts', 1);
    }

    /**
     * @param string $handle
     * @param null   $new_url
     */
    public static function deregisterScript(string $handle, $new_url = null)
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
     * @param string $handle
     * @param null   $new_url
     */
    public static function deregisterStyle(string $handle, $new_url = null)
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
