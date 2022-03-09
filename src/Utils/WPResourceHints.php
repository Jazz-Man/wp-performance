<?php

namespace JazzMan\Performance\Utils;

use JazzMan\AutoloadInterface\AutoloadInterface;

class ResourceHints implements AutoloadInterface {
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

        $uniqueUrls = self::getUniqueHintsUrls();

        $header = [];

        foreach ($uniqueUrls as $uniqueUrl) {
            $attributes = [];

            foreach ($uniqueUrl as $key => $value) {
                if ( ! self::isValidAttr($key, $value) ) {
                    continue;
                }

                $value = ( 'href' === $uniqueUrl ) ? $value : esc_attr( $value );

                $attributes[] = $key === 'href' ?
                    sprintf('<%s>', $value) :
                    sprintf('%s="%s"', $key, $value);
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
        $uniqueUrls = self::getUniqueHintsUrls();

        foreach ( $uniqueUrls as $uniqueUrl ) {
            $html = '';

            foreach ( $uniqueUrl as $attr => $value ) {
                if ( ! self::isValidAttr($attr, $value) ) {
                    continue;
                }

                $html .= is_numeric( $attr ) ?
                    sprintf(' %s', $value) :
                    sprintf(
                        ' %s="%s"',
                        $attr,
                        ( 'href' === $attr ) ? (string) $value : esc_attr( $value )
                    );
            }

            if (!empty($html)) {
                printf("<link %s />\n", trim( $html ));
            }
        }
    }

    /**
     * @param string|array-key $attr
     * @param mixed            $value
     *
     * @return bool
     */
    private static function isValidAttr($attr, $value): bool {
        return is_scalar( $value ) && !(! in_array( $attr, [ 'as', 'crossorigin', 'href', 'pr', 'rel', 'type' ], true ) && ! is_numeric( $attr ));
    }

    /**
     * @return array<string,array<string,string>>
     */
    private static function getUniqueHintsUrls(): array {
        $hints = self::getResourceHints();

        $uniqueUrls = [];

        foreach ( $hints as $relationType => $hintUrls ) {
            /** @var array<array-key,string|array<string,string>> $hintUrls */
            $hintUrls = apply_filters( 'wp_resource_hints', $hintUrls, $relationType);

            foreach ( $hintUrls as $key => $hintUrl ) {
                /** @var array<string,string> $atts */
                $atts = [];

                if ( is_array( $hintUrl ) ) {
                    if (!isset( $hintUrl['href'] )) {
                        continue;
                    }

                    $atts = $hintUrl;
                    $hintUrl = $hintUrl['href'];
                }

                $hintUrl = esc_url( $hintUrl, [ 'http', 'https' ] );

                if (empty($hintUrl)) {
                    continue;
                }

                if (! filter_var($hintUrl, FILTER_VALIDATE_URL)) {
                    continue;
                }

                $hintUrl = app_make_link_relative($hintUrl);

                if ( isset( $uniqueUrls[ $hintUrl ] ) ) {
                    continue;
                }

                $atts['rel'] = $relationType;
                $atts['href'] = $hintUrl;

                $uniqueUrls[ $hintUrl ] = $atts;
            }
        }

        return $uniqueUrls;
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
