<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;

/**
 * Class Enqueue.
 */
class Enqueue implements AutoloadInterface {
    public function load(): void {
        add_action('wp_enqueue_scripts', [self::class, 'jsToFooter']);
        add_action('wp_enqueue_scripts', [self::class, 'jqueryFromCdn']);
        add_filter('style_loader_tag', [self::class, 'addAsyncStyle'], 10, 4);
        add_filter('script_loader_tag', [self::class, 'addAsyncScript'], 10, 2);

        if (!is_admin()) {
            add_filter('script_loader_src', [self::class, 'setScriptVersion'], 15, 2);
            add_filter('style_loader_src', [self::class, 'setScriptVersion'], 15, 2);
        }
    }

    public static function addAsyncStyle(string $tag, string $handle, string $href, string $media): string {
        if ('print' === $media) {
            return sprintf(
                '<link rel="%s" id="%s-css" href="%s" media="%s" onload="this.media=\'all\'; this.onload=null;" />',
                'stylesheet',
                $handle,
                $href,
                $media
            );
        }

        return $tag;
    }

    public static function addAsyncScript(string $tag, string $handle): string {
        if (is_admin()) {
            return $tag;
        }

        $methods = [
            'async' => ['polyfill.io'],
            'defer' => ['google-recaptcha'],
        ];

        foreach ($methods as $method => $handlers) {
            /** @var string[] $validHandlers */
            $validHandlers = apply_filters("app_{$method}_scripts_handlers", $handlers);

            if (empty($validHandlers)) {
                continue;
            }

            if (!\in_array($handle, $validHandlers, true)) {
                continue;
            }

            $tag = str_replace(' src', " {$method} src", $tag);
        }

        return $tag;
    }

    public static function setScriptVersion(string $scriptSrc, string $handle): string {
        if (!filter_var($scriptSrc, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
            return $scriptSrc;
        }

        $addVersion = (bool) apply_filters('enqueue_add_script_version', true, $handle);

        if ($addVersion && app_is_current_host($scriptSrc)) {
            $file = self::prepareScriptFilePath($scriptSrc);

            if (!empty($file)) {
                $fileMap = $file.'.map';

                $timestamp = is_readable($fileMap) ? $fileMap : $file;

                $scriptSrc = add_query_arg([
                    'ver' => filemtime((string) $timestamp),
                ], $scriptSrc);
            }
        } elseif (strpos($scriptSrc, '?ver=')) {
            $scriptSrc = remove_query_arg('ver', $scriptSrc);
        }

        return app_make_link_relative($scriptSrc);
    }

    public static function jqueryFromCdn(): void {
        $registered = wp_scripts()->registered;

        $jqCore = $registered['jquery-core'];

        $jsdelivrUrl = 'https://cdn.jsdelivr.net/npm/jquery-ui@1.12.1';

        foreach (array_keys($registered) as $handle) {
            if (0 === strpos((string) $handle, 'jquery-effects-')) {
                $isCore = 'jquery-effects-core' === $handle;

                $newUrl = sprintf(
                    '%s/ui/effect%s.min.js',
                    $jsdelivrUrl,
                    $isCore ? '' : str_replace('jquery-effects-', '-', (string) $handle)
                );

                self::deregisterScript((string) $handle, $newUrl);
            }

            if (0 === strpos((string) $handle, 'jquery-ui-')) {
                switch ($handle) {
                    case 'jquery-ui-core':
                        $newUrl = sprintf('%s/ui/core.min.js', $jsdelivrUrl);

                        break;

                    case 'jquery-ui-widget':
                        $newUrl = sprintf('%s/ui/widget.min.js', $jsdelivrUrl);

                        break;

                    default:
                        $newUrl = sprintf(
                            '%s/ui/widgets/%s.min.js',
                            $jsdelivrUrl,
                            str_replace('jquery-ui-', '', (string) $handle)
                        );

                        break;
                }

                self::deregisterScript((string) $handle, $newUrl);
            }
        }

        $jqVer = trim((string) $jqCore->ver, '-wp');

        self::deregisterScript($jqCore->handle, sprintf('https://code.jquery.com/jquery-%s.min.js', esc_attr($jqVer)));
        self::deregisterScript('jquery');

        wp_register_script('jquery', false, [$jqCore->handle], $jqVer, true);
    }

    public static function jsToFooter(): void {
        remove_action('wp_head', 'wp_print_scripts');
        remove_action('wp_head', 'wp_print_head_scripts', 9);
        remove_action('wp_head', 'wp_enqueue_scripts', 1);
    }

    public static function deregisterScript(string $handle, ?string $newUrl = null, bool $enqueue = false): void {
        $registered = wp_scripts()->registered;

        if (!empty($registered[$handle])) {
            $jsLib = $registered[$handle];

            wp_dequeue_script($jsLib->handle);
            wp_deregister_script($jsLib->handle);

            if (!empty($newUrl)) {
                /** @var callable $function */
                $function = $enqueue ? 'wp_enqueue_script' : 'wp_register_script';

                $function($jsLib->handle, $newUrl, $jsLib->deps, $jsLib->ver, true);
            }
        }
    }

    public static function deregisterStyle(string $handle, ?string $newUrl = null, bool $enqueue = false): void {
        $registered = wp_styles()->registered;

        if (!empty($registered[$handle])) {
            $cssLib = $registered[$handle];

            wp_dequeue_style($cssLib->handle);
            wp_deregister_style($cssLib->handle);

            if (!empty($newUrl)) {
                /** @var callable $function */
                $function = $enqueue ? 'wp_enqueue_style' : 'wp_register_style';
                $function($cssLib->handle, $newUrl, $cssLib->deps, $cssLib->ver);
            }
        }
    }

    /**
     * @return bool|string
     */
    private static function prepareScriptFilePath(string $scriptSrc) {
        $rootDir = app_locate_root_dir();

        if (empty($rootDir)) {
            return false;
        }

        $path = ltrim((string) parse_url($scriptSrc, PHP_URL_PATH), '/');

        $file = sprintf('%s/%s', $rootDir, self::fixMultiSitePath($path));

        return is_readable($file) ? $file : false;
    }

    private static function fixMultiSitePath(string $path): string {
        static $blogDetails;

        if (!is_multisite()) {
            return $path;
        }

        if (null === $blogDetails) {
            $blogDetails = get_blog_details(null, false);
        }

        if (!$blogDetails instanceof \WP_Site) {
            return $path;
        }

        return ltrim($path, $blogDetails->path);
    }
}
