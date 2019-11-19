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
        add_action('wp_enqueue_scripts', [$this, 'jsToFooter']);
        add_action('wp_enqueue_scripts', [$this, 'jqueryFromCdn']);

        if (!is_admin()) {
            add_filter('script_loader_src', [$this, 'addScriptVersion'], 15, 1);
            add_filter('style_loader_src', [$this, 'addScriptVersion'], 15, 1);
        }
    }

    /**
     * @param string $src
     *
     * @return string
     */
    public function addScriptVersion($src)
    {
        if (!empty($src)) {
            $current_host = (string) $_SERVER['HTTP_HOST'];
            $url_host = parse_url($src, PHP_URL_HOST);

            if (!empty($url_host) && $current_host === $url_host) {
                $path = parse_url($src, PHP_URL_PATH);
                $root = App::getRootDir();
                $file = "{$root}{$path}";

                if (is_file($file)) {
                    $timestamp = is_file("{$file}.map") ? filemtime("{$file}.map") : filemtime($file);

                    $src = add_query_arg([
                        'ver' => $timestamp,
                    ], $src);

                    return esc_url($src);
                }
            }
        }

        return $src;
    }

    public static function jqueryFromCdn()
    {
        $registered_scripts = wp_scripts()->registered;

        /** @var \_WP_Dependency $jquery_core */
        $jquery_core = $registered_scripts['jquery-core'];

        $jquery_ver = trim($jquery_core->ver, '-wp');

        self::deregisterScript($jquery_core->handle, "https://ajax.googleapis.com/ajax/libs/jquery/{$jquery_ver}/jquery.min.js");
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

        if (!empty($registered_scripts[$handle])) {
            /** @var \_WP_Dependency $js_lib */
            $js_lib = $registered_scripts[$handle];

            wp_dequeue_script($js_lib->handle);
            wp_deregister_script($js_lib->handle);

            if (!empty($new_url)) {
                wp_register_script($js_lib->handle, $new_url, $js_lib->deps, $js_lib->ver, true);

                if (!empty($js_lib->extra) && \is_array($js_lib->extra)) {
                    foreach ($js_lib->extra as $position => $data) {
                        wp_add_inline_script($js_lib->handle, end($data), $position);
                    }
                }
            }
        }
    }
}
