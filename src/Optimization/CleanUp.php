<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;

/**
 * Class CleanUp.
 */
final class CleanUp implements AutoloadInterface {

    public function load(): void {
        add_action( 'init', self::headCleanup( ... ) );
        // Remove the WordPress version from RSS feeds
        add_filter( 'the_generator', '__return_false' );

        add_filter( 'language_attributes', self::languageAttributes( ... ) );

        /*
         * Wrap embedded media as suggested by Readability.
         *
         * @see https://gist.github.com/965956
         * @see http://www.readability.com/publishers/guidelines#publisher
         *
         */
        add_filter( 'embed_oembed_html', static fn ( string $cache ): string => '<div class="entry-content-asset">'.$cache.'</div>' );

        // Don't return the default description in the RSS feed if it hasn't been changed.
        add_filter( 'get_bloginfo_rss', static fn ( string $bloginfo ): string => ( 'Just another WordPress site' === $bloginfo ) ? '' : $bloginfo );

        $this->protectXmlrpc();
    }

    public static function headCleanup(): void {
        self::cleanupWpHead();

        remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
        remove_action( 'template_redirect', 'rest_output_link_header', 11 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' );
        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
        add_filter( 'use_default_gallery_style', '__return_false' );
        add_filter( 'emoji_svg_url', '__return_false' );
        add_filter( 'show_recent_comments_widget_style', '__return_false' );
        add_filter( 'rest_queried_resource_route', '__return_empty_string' );
    }

    public static function languageAttributes(): string {
        $attributes = [];

        if ( is_rtl() ) {
            $attributes['dir'] = 'rtl';
        }

        $lang = get_bloginfo( 'language' );

        if ( ! empty( $lang ) ) {
            $attributes['lang'] = $lang;
        }

        return app_add_attr_to_el( $attributes );
    }

    private function protectXmlrpc(): void {
        add_filter( 'xmlrpc_enabled', '__return_false' );
        add_filter( 'pre_update_option_enable_xmlrpc', '__return_false' );
        add_filter( 'pre_option_enable_xmlrpc', '__return_zero' );
        add_filter( 'pings_open', '__return_false', 10, 2 );

        $disableMethods = [
            'pingback.ping',
            'pingback.extensions.getPingbacks',
            'wp.getUsersBlogs',
            'system.multicall',
            'system.listMethods',
            'system.getCapabilities',
        ];

        /**
         * Disable XMLRPC call.
         */
        add_action( 'xmlrpc_call', static function ( string $action ) use ( $disableMethods ): void {
            if ( \in_array( $action, $disableMethods, true ) ) {
                wp_die(
                    sprintf( '%s are not supported', $action ),
                    'Not Allowed!',
                    ['response' => 403]
                );
            }
        } );

        /**
         * Disable pingback XMLRPC method.
         */
        add_filter( 'xmlrpc_methods', static function ( array $methods ) use ( $disableMethods ): array {
            foreach ( $disableMethods as $disableMethod ) {
                if ( ! empty( $methods[$disableMethod] ) ) {
                    unset( $methods[$disableMethod] );
                }
            }

            return $methods;
        } );

        add_filter( 'bloginfo_url', static fn ( string $output, string $show ): string => 'pingback_url' === $show ? '' : $output, 10, 2 );

        /**
         * Remove pingback header.
         */
        add_filter( 'wp_headers', static function ( array $headers ): array {
            if ( isset( $headers['X-Pingback'] ) ) {
                unset( $headers['X-Pingback'] );
            }

            return $headers;
        } );

        add_filter( 'register_post_type_args', static function ( array $args ): array {
            if ( ! empty( $args['_builtin'] ) && ! empty( $args['supports'] ) ) {
                $args['supports'] = array_merge( array_diff( $args['supports'], ['trackbacks'] ) );
            }

            return $args;
        }, PHP_INT_MAX );

        /**
         * Kill trackback rewrite rule.
         */
        add_filter( 'rewrite_rules_array', static function ( array $rules ): array {
            foreach ( array_keys( $rules ) as $rule ) {
                if ( preg_match( '#trackback\/\?\$$#i', (string) $rule ) ) {
                    unset( $rules[$rule] );
                }
            }

            return $rules;
        } );
    }

    /**
     * Originally from http://wpengineer.com/1438/wordpress-header/.
     */
    private static function cleanupWpHead(): void {
        remove_action( 'wp_head', 'feed_links', 2 );
        remove_action( 'wp_head', 'feed_links_extra', 3 );
        remove_action( 'wp_head', 'rest_output_link_wp_head' );
        remove_action( 'wp_head', 'rsd_link' );
        remove_action( 'wp_head', 'wlwmanifest_link' );
        remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
        remove_action( 'wp_head', 'wp_generator' );
        remove_action( 'wp_head', 'wp_shortlink_wp_head' );
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
        remove_action( 'wp_head', 'wp_oembed_add_host_js' );
    }
}
