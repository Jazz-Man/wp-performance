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


        if (!is_admin()){
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
            $url_host = parse_url($src,PHP_URL_HOST);

            if (!empty($url_host) && $current_host === $url_host) {
                $path = parse_url($src,PHP_URL_PATH);
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

    public function jsToFooter()
    {
        remove_action('wp_head', 'wp_print_scripts');
        remove_action('wp_head', 'wp_print_head_scripts', 9);
        remove_action('wp_head', 'wp_enqueue_scripts', 1);
    }
}
