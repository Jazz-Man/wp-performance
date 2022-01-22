<?php

namespace JazzMan\Performance\Optimization;

use _WP_Dependency;
use JazzMan\AutoloadInterface\AutoloadInterface;
use Symfony\Component\WebLink\Link;
use WP_Site;

/**
 * Class Enqueue.
 */
class Enqueue implements AutoloadInterface {
    public function load(): void {
        add_filter('app_preload_links', fn (array $links): array => $this->preloadLinks($links));
        add_action('wp_enqueue_scripts', function (): void {
            $this->jsToFooter();
        });
        add_action('wp_enqueue_scripts', function (): void {
            $this::jqueryFromCdn();
        });

        if ( ! is_admin()) {
            add_filter('script_loader_src', fn (string $scriptSrc, string $handle): string => $this->setScriptVersion($scriptSrc, $handle), 15, 2);
            add_filter('style_loader_src', fn (string $scriptSrc, string $handle): string => $this->setScriptVersion($scriptSrc, $handle), 15, 2);
        }
    }

    /**
     * @param Link[] $links
     *
     * @return Link[]
     *
     * @psalm-return array<Link>
     */
    public function preloadLinks(array $links): array {
        $links[] = Http::dnsPrefetchLink('https://code.jquery.com');

        return $links;
    }

    public function setScriptVersion(string $scriptSrc, string $handle): string {
        if ( ! filter_var($scriptSrc, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
            return $scriptSrc;
        }

        $addVersion = (bool) apply_filters('enqueue_add_script_version', true, $handle);

        if ($addVersion && app_is_current_host($scriptSrc)) {
            $file = self::prepareScriptFilePath($scriptSrc);

            if (!empty($file)) {
                $fileMap = sprintf('%s.map', (string) $file);

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

    /**
     * @return string|bool
     */
    private static function prepareScriptFilePath(string $scriptSrc) {
        $path = ltrim((string) parse_url($scriptSrc, PHP_URL_PATH), '/');

        $file = sprintf(
            '%s/%s',
            (string) app_locate_root_dir(),
            self::fixMultiSitePath($path)
        );

        return is_readable($file) ? $file : false;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private static function fixMultiSitePath(string $path): string {
        static $blogDetails;

        if ($blogDetails === null) {
            $blogDetails = get_blog_details(null, false);
        }

        return is_multisite() && $blogDetails instanceof WP_Site ? ltrim($path, $blogDetails->path) : $path;
    }

    public static function jqueryFromCdn(): void {
        $scripts = wp_scripts();

        /** @var _WP_Dependency $jqCore */
        $jqCore = $scripts->registered['jquery-core'];

        $jsdelivrUrl = 'https://cdn.jsdelivr.net/npm/jquery-ui@1.12.1';

        foreach ($scripts->registered as $handle => $data) {
            /* @var _WP_Dependency $data */
            if (strpos($handle, 'jquery-effects-') === 0) {
                if ($handle === 'jquery-effects-core') {
                    $newUrl = sprintf('%s/ui/effect.min.js', $jsdelivrUrl);
                } else {
                    $newUrl = sprintf(
                        '%s/ui/effects/effect-%s.min.js',
                        $jsdelivrUrl,
                        str_replace('jquery-effects-', '', $handle)
                    );
                }

                self::deregisterScript($handle, $newUrl);
            }

            if (strpos($handle, 'jquery-ui-') === 0) {
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
                            str_replace('jquery-ui-', '', $handle)
                        );
                        break;

                }
                self::deregisterScript($handle, $newUrl);
            }
        }

        $jqVer = trim((string) $jqCore->ver, '-wp');

        self::deregisterScript($jqCore->handle, sprintf('https://code.jquery.com/jquery-%s.min.js', esc_attr($jqVer)));
        self::deregisterScript('jquery');

        wp_register_script('jquery', false, [$jqCore->handle], $jqVer, true);
    }

    public function jsToFooter(): void {
        remove_action('wp_head', 'wp_print_scripts');
        remove_action('wp_head', 'wp_print_head_scripts', 9);
        remove_action('wp_head', 'wp_enqueue_scripts', 1);
    }

    public static function deregisterScript(string $handle, ?string $newUrl = null, bool $enqueue = false): void {
        $registered = wp_scripts()->registered;

        if ( ! empty($registered[$handle])) {
            /** @var _WP_Dependency $jsLib */
            $jsLib = $registered[$handle];

            wp_dequeue_script($jsLib->handle);
            wp_deregister_script($jsLib->handle);

            if ( ! empty($newUrl)) {
                wp_register_script($jsLib->handle, $newUrl, $jsLib->deps, $jsLib->ver, true);

                if ($enqueue) {
                    wp_enqueue_script($jsLib->handle);
                }
            }
        }
    }

    public static function deregisterStyle(string $handle, ?string $newUrl = null): void {
        $registered = wp_styles()->registered;

        if ( ! empty($registered[$handle])) {
            /** @var _WP_Dependency $cssLib */
            $cssLib = $registered[$handle];

            wp_dequeue_style($cssLib->handle);
            wp_deregister_style($cssLib->handle);

            if ( ! empty($newUrl)) {
                wp_register_style($cssLib->handle, $newUrl, $cssLib->deps, $cssLib->ver);
            }
        }
    }
}
