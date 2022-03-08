<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;

class WPResourceHints implements AutoloadInterface {
    public function load() {
        add_action('init', [__CLASS__, 'removeWpResourceHints']);
        add_action('template_redirect', [__CLASS__, 'preloadLinksHttpHeader' ]);
        add_action('wp_head', [__CLASS__, 'preloadLinks']);
    }

    public static function removeWpResourceHints(): void {
        remove_action('wp_head', 'wp_resource_hints', 2);
    }

    public static function preloadLinksHttpHeader(): void {
        if (\headers_sent()) {
            return;
        }

        $unique_urls = self::getUniqueHintsUrls();

        $header = [];

        foreach ($unique_urls as $attr) {
            $attributes = [];

            foreach ($attr as $key => $value) {
                if ( ! self::isValidAttr($key, $value) ) {
                    continue;
                }

                $value = ( 'href' === $attr ) ? $value : esc_attr( $value );

                $attributes[] = $key === 'href' ? sprintf('<%s>', $value) : sprintf('%s="%s"', $key, $value);
            }

            if (!empty($attributes)) {
                $header[] = implode('; ', $attributes);
            }
        }

        if (!empty($header)) {
            header(sprintf('Link: %s', implode(', ', $header)), false);
        }
    }

    public static function preloadLinks(): void {
        $unique_urls = self::getUniqueHintsUrls();

        foreach ( $unique_urls as $atts ) {
            $html = '';

            foreach ( $atts as $attr => $value ) {
                if ( ! self::isValidAttr($attr, $value) ) {
                    continue;
                }

                $html .= ! is_string( $attr ) ? " $value" : sprintf(
                    ' %s="%s"',
                    $attr,
                    ( 'href' === $attr ) ? $value : esc_attr( $value )
                );
            }

            if (!empty($html)) {
                printf("<link %s />\n", trim( $html ));
            }
        }
    }

    /**
     * @param string $attr
     * @param mixed  $value
     *
     * @return bool
     */
    private static function isValidAttr(string $attr, $value): bool {
        if ( ! is_scalar( $value ) || ( ! in_array( $attr, [ 'as', 'crossorigin', 'href', 'pr', 'rel', 'type' ], true ) && ! is_numeric( $attr ) ) ) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string,array<string,string>>
     */
    private static function getUniqueHintsUrls(): array {
        $hints = self::getResourceHints();

        $unique_urls = [];

        foreach ( $hints as $relation_type => $urls ) {
            /** @var array<string,string|array<string,string>> $urls */
            $urls = apply_filters( 'wp_resource_hints', $urls, $relation_type );

            foreach ( $urls as $key => $url ) {
                $atts = [];

                if ( is_array( $url ) ) {
                    if ( isset( $url['href'] ) ) {
                        $atts = $url;
                        $url = $url['href'];
                    } else {
                        continue;
                    }
                }

                $url = esc_url( $url, [ 'http', 'https' ] );

                if ( ! $url ) {
                    continue;
                }

                if ( ! filter_var($url, FILTER_VALIDATE_URL)) {
                    continue;
                }

                $url = app_make_link_relative($url);

                if ( isset( $unique_urls[ $url ] ) ) {
                    continue;
                }

                $atts['rel'] = $relation_type;
                $atts['href'] = $url;

                $unique_urls[ $url ] = $atts;
            }
        }

        return $unique_urls;
    }

    /**
     * @return array<string,string[]|array<string,string>>
     */
    private static function getResourceHints(): array {
        static $hints;

        if ($hints === null) {
            $hints = [
                'dns-prefetch' => wp_dependencies_unique_hosts(),
                'preconnect' => [],
                'prefetch' => [],
                'prerender' => [],
                'preload' => [],
            ];
        }

        return $hints;
    }
}
