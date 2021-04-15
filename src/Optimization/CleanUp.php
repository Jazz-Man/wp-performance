<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;

/**
 * Class CleanUp.
 */
class CleanUp implements AutoloadInterface
{
    public function load()
    {
        add_action('init', [$this, 'headCleanup']);
        /*
         * Remove the WordPress version from RSS feeds
         */
        add_filter('the_generator', '__return_false');
        add_filter('xmlrpc_enabled', '__return_false');

        add_filter('language_attributes', [$this, 'languageAttributes']);
        add_filter('body_class', [$this, 'bodyClass']);
        add_filter('embed_oembed_html', [$this, 'embedWrap']);
        add_filter('get_bloginfo_rss', [$this, 'removeDefaultDescription']);
        add_filter('xmlrpc_methods', [$this, 'filterXmlrpcMethod']);
        add_filter('wp_headers', [$this, 'filterHeaders']);
        add_filter('rewrite_rules_array', [$this, 'filterRewrites']);
        add_filter('bloginfo_url', [$this, 'killPingbackUrl'], 10, 2);
        add_action('xmlrpc_call', [$this, 'killXmlrpc']);
    }

    public function headCleanup()
    {
        // Originally from http://wpengineer.com/1438/wordpress-header/
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
        remove_action('wp_head', 'rest_output_link_wp_head', 10);
        remove_action('template_redirect', 'wp_shortlink_header', 11);
        remove_action('template_redirect', 'rest_output_link_header', 11);
        add_action('wp_head', 'ob_start', 1, 0);
        add_action('wp_head', static function () {
            $pattern = '/.*'.\preg_quote(esc_url(get_feed_link('comments_'.get_default_feed())), '/').'.*[\r\n]+/';
            echo \preg_replace($pattern, '', \ob_get_clean());
        }, 3, 0);
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        add_filter('use_default_gallery_style', '__return_false');
        add_filter('emoji_svg_url', '__return_false');
        add_filter('show_recent_comments_widget_style', '__return_false');
        add_filter('rest_queried_resource_route', '__return_empty_string');
    }

    /**
     * @return string
     */
    public function languageAttributes(): string
    {
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
     * Add and remove body_class() classes.
     *
     * @param array $classes
     *
     * @return array
     */
    public function bodyClass(array $classes): array
    {
        // Add post/page slug if not present
        if (is_single() || (is_page() && ! is_front_page())) {
            $permalink = get_permalink();

            if (! \in_array(\basename($permalink), $classes)) {
                $classes[] = \basename($permalink);
            }
        }
        // Remove unnecessary classes

        $remove_classes = [
            'page-template-default',
            sprintf('page-id-%d',esc_attr(get_option('page_on_front'))),
        ];

        return \array_diff($classes, $remove_classes);
    }

    /**
     * Wrap embedded media as suggested by Readability.
     *
     * @see https://gist.github.com/965956
     * @see http://www.readability.com/publishers/guidelines#publisher
     *
     * @param $cache
     *
     * @return string
     */
    public function embedWrap($cache): string
    {
        return '<div class="entry-content-asset">'.$cache.'</div>';
    }

    /**
     * Don't return the default description in the RSS feed if it hasn't been changed.
     *
     * @param string $bloginfo
     *
     * @return string
     */
    public function removeDefaultDescription(string $bloginfo): string
    {
        $default_tagline = 'Just another WordPress site';

        return ($bloginfo === $default_tagline) ? '' : $bloginfo;
    }

    /**
     * Disable pingback XMLRPC method.
     *
     * @param array $methods
     *
     * @return array
     */
    public function filterXmlrpcMethod(array $methods): array
    {
        unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);

        return $methods;
    }

    /**
     * Remove pingback header.
     *
     * @param array $headers
     *
     * @return array
     */
    public function filterHeaders(array $headers): array
    {
        if (isset($headers['X-Pingback'])) {
            unset($headers['X-Pingback']);
        }

        return $headers;
    }

    /**
     * Kill trackback rewrite rule.
     *
     * @param array $rules
     *
     * @return array
     */
    public function filterRewrites(array $rules): array
    {
        foreach ($rules as $rule => $rewrite) {
            if (\preg_match('/trackback\/\?\$$/i', $rule)) {
                unset($rules[$rule]);
            }
        }

        return $rules;
    }

    /**
     * Kill bloginfo('pingback_url').
     *
     * @param string $output
     * @param string $show
     *
     * @return string
     */
    public function killPingbackUrl(string $output, string $show): string
    {
        if ('pingback_url' === $show) {
            $output = '';
        }

        return $output;
    }

    /**
     * Disable XMLRPC call.
     *
     * @param string $action
     */
    public function killXmlrpc(string $action)
    {
        if ('pingback.ping' === $action) {
            wp_die('Pingbacks are not supported', 'Not Allowed!', ['response' => 403]);
        }
    }
}
