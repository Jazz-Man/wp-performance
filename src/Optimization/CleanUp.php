<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;

/**
 * Class CleanUp.
 */
class CleanUp implements AutoloadInterface {
    public function load(): void {
        add_action('init', [__CLASS__, 'headCleanup']);
        // Remove the WordPress version from RSS feeds
        add_filter('the_generator', '__return_false');
        add_filter('xmlrpc_enabled', '__return_false');

        add_filter('language_attributes', [__CLASS__, 'languageAttributes']);

        /*
         * Wrap embedded media as suggested by Readability.
         *
         * @see https://gist.github.com/965956
         * @see http://www.readability.com/publishers/guidelines#publisher
         *
         */
        add_filter('embed_oembed_html', static fn (string $cache): string => '<div class="entry-content-asset">' . $cache . '</div>');

        // Don't return the default description in the RSS feed if it hasn't been changed.
        add_filter('get_bloginfo_rss', static fn (string $bloginfo): string => ('Just another WordPress site' === $bloginfo) ? '' : $bloginfo);
        add_filter('xmlrpc_methods', [__CLASS__, 'filterXmlrpcMethod']);
        add_filter('wp_headers', [__CLASS__, 'filterHeaders']);
        add_filter('rewrite_rules_array', [__CLASS__, 'filterRewrites']);
        add_filter('bloginfo_url', [__CLASS__, 'killPingbackUrl'], 10, 2);
        add_action('xmlrpc_call', [__CLASS__, 'killXmlrpc']);
    }

    public static function headCleanup(): void {
        self::cleanupWpHead();

        remove_action('template_redirect', 'wp_shortlink_header', 11);
        remove_action('template_redirect', 'rest_output_link_header', 11);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        add_filter('use_default_gallery_style', '__return_false');
        add_filter('emoji_svg_url', '__return_false');
        add_filter('show_recent_comments_widget_style', '__return_false');
        add_filter('rest_queried_resource_route', '__return_empty_string');
    }

    /**
     * Originally from http://wpengineer.com/1438/wordpress-header/.
     */
    private static function cleanupWpHead(): void {
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
        remove_action('wp_head', 'rest_output_link_wp_head');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
    }

    public static function languageAttributes(): string {
        $attributes = [];

        if (is_rtl()) {
            $attributes['dir'] = 'rtl';
        }

        $lang = get_bloginfo('language');

        if ($lang) {
            $attributes['lang'] = $lang;
        }

        return app_add_attr_to_el($attributes);
    }

    /**
     * Disable pingback XMLRPC method.
     *
     * @param array<string,string> $methods
     *
     * @return array<string,string>
     */
    public static function filterXmlrpcMethod(array $methods): array {
        unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);

        return $methods;
    }

    /**
     * Remove pingback header.
     *
     * @param array<string,string> $headers
     *
     * @return array<string,string>
     */
    public static function filterHeaders(array $headers): array {
        if (isset($headers['X-Pingback'])) {
            unset($headers['X-Pingback']);
        }

        return $headers;
    }

    /**
     * Kill trackback rewrite rule.
     *
     * @param array<string,string> $rules
     *
     * @return array<string,string>
     */
    public static function filterRewrites(array $rules): array {
        foreach (array_keys($rules) as $rule) {
            if (preg_match('#trackback\/\?\$$#i', $rule)) {
                unset($rules[$rule]);
            }
        }

        return $rules;
    }

    /**
     * Kill bloginfo('pingback_url').
     */
    public function killPingbackUrl(string $output, string $show): string {
        return 'pingback_url' === $show ? '' : $output;
    }

    /**
     * Disable XMLRPC call.
     */
    public static function killXmlrpc(string $action): void {
        if ('pingback.ping' === $action) {
            wp_die('Pingbacks are not supported', 'Not Allowed!', ['response' => 403]);
        }
    }
}
