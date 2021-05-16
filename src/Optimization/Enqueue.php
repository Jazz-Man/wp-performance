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

        if (!is_admin()) {
            add_filter('script_loader_src', [$this, 'setScriptVersion'], 15, 2);
            add_filter('style_loader_src', [$this, 'setScriptVersion'], 15, 2);
        }
    }

    public function preloadLinks(array $links): array
    {
        $links[] = Http::dnsPrefetchLink('https://code.jquery.com');

        return $links;
    }

    public function setScriptVersion(string $src, string $handle): string
    {
        $isCurrentHost = app_is_current_host($src);

        if (\filter_var($src, FILTER_VALIDATE_URL)) {
            $addVersion = (bool) apply_filters('enqueue_add_script_version', true, $handle);

            if ($addVersion) {
                if ($isCurrentHost) {
                    $path = \parse_url($src, PHP_URL_PATH);
                    $path = \ltrim($path, '/');
                    $root = app_locate_root_dir();

                    if (is_multisite() && ($blog = get_blog_details(null, false))) {
                        $path = \ltrim($path, $blog->path);
                    }

                    $file = "{$root}/{$path}";

                    if (\is_readable($file)) {
                        $timestamp = \is_readable("{$file}.map") ? \filemtime("{$file}.map") : \filemtime($file);

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

        if ($isCurrentHost) {
            return wp_make_link_relative($src);
        }

        return $src;
    }

    public static function jqueryFromCdn(): void
    {
        /** @var \_WP_Dependency $jqCore */
        $jqCore = wp_scripts()->registered['jquery-core'];

        $jqVer = \trim($jqCore->ver, '-wp');

        self::deregisterScript($jqCore->handle, sprintf('https://code.jquery.com/jquery-%s.min.js', esc_attr($jqVer)));
        self::deregisterScript('jquery');

        wp_register_script('jquery', false, [$jqCore->handle], $jqVer, true);
    }

    public function jsToFooter(): void
    {
        remove_action('wp_head', 'wp_print_scripts');
        remove_action('wp_head', 'wp_print_head_scripts', 9);
        remove_action('wp_head', 'wp_enqueue_scripts', 1);
    }

    public static function deregisterScript(string $handle, string $newUrl = null): void
    {
        $registered = wp_scripts()->registered;

        if (!empty($registered[$handle])) {
            /** @var \_WP_Dependency $jsLib */
            $jsLib = $registered[$handle];

            wp_dequeue_script($jsLib->handle);
            wp_deregister_script($jsLib->handle);

            if (!empty($newUrl)) {
                wp_register_script($jsLib->handle, $newUrl, $jsLib->deps, $jsLib->ver, true);
            }
        }
    }

    public static function deregisterStyle(string $handle, string $newUrl = null)
    {
        $registered = wp_styles()->registered;

        if (!empty($registered[$handle])) {
            /** @var \_WP_Dependency $cssLib */
            $cssLib = $registered[$handle];

            wp_dequeue_style($cssLib->handle);
            wp_deregister_style($cssLib->handle);

            if (!empty($newUrl)) {
                wp_register_style($cssLib->handle, $newUrl, $cssLib->deps, $cssLib->ver, $cssLib->args);
            }
        }
    }
}
