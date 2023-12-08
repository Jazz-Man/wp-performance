<?php

namespace JazzMan\Performance\Utils;

use JazzMan\AutoloadInterface\AutoloadInterface;

final class ResourceHints implements AutoloadInterface {

    public function load(): void {
        add_action( 'init', self::removeWpResourceHints( ... ) );
        add_action( 'template_redirect', self::preloadLinksHttpHeader( ... ) );
        add_action( 'wp_head', self::preloadLinks( ... ) );
    }

    public static function removeWpResourceHints(): void {
        remove_action( 'wp_head', 'wp_resource_hints', 2 );
    }

    public static function preloadLinksHttpHeader(): void {
        if ( headers_sent() ) {
            return;
        }

        $uniqueUrls = self::getUniqueHintsUrls();

        $header = [];

        foreach ( $uniqueUrls as $uniqueUrl ) {
            $attributes = [];

            foreach ( $uniqueUrl as $attr => $value ) {
                if ( ! self::isValidAttr( $attr, $value ) ) {
                    continue;
                }

                $attributes[] = 'href' === $attr ?
                    sprintf( '<%s>', esc_url( $value ) ) :
                    sprintf( '%s="%s"', $attr, esc_attr( $value ) );
            }

            if ( ! empty( $attributes ) ) {
                $header[] = implode( '; ', $attributes );
            }

            unset( $attributes );
        }

        unset( $uniqueUrls );

        if ( ! empty( $header ) ) {
            header( sprintf( 'Link: %s', implode( ', ', $header ) ), false );
        }
    }

    public static function preloadLinks(): void {
        $uniqueUrls = self::getUniqueHintsUrls();

        foreach ( $uniqueUrls as $uniqueUrl ) {
            $html = '';

            foreach ( $uniqueUrl as $attr => $value ) {
                if ( ! self::isValidAttr( $attr, $value ) ) {
                    continue;
                }

                $html .= is_numeric( $attr ) ?
                    sprintf( ' %s', $value ) :
                    sprintf(
                        ' %s="%s"',
                        $attr,
                        'href' === $attr ? esc_url( $value ) : esc_attr( $value )
                    );
            }

            if ( ! empty( $html ) ) {
                printf( "<link %s />\n", trim( $html ) );
            }

            unset( $html );
        }

        unset( $uniqueUrls );
    }

    /**
     * @param array-key|string $attr
     */
    private static function isValidAttr( mixed $attr, mixed $value ): bool {
        return \is_scalar( $value ) && ! ( ! \in_array( $attr, [ 'as', 'crossorigin', 'href', 'pr', 'rel', 'type' ], true ) && ! is_numeric( $attr ) );
    }

    /**
     * @return array<string,array<string,string>>
     */
    private static function getUniqueHintsUrls(): array {
        $hints = self::getResourceHints();

        $uniqueUrls = [];

        foreach ( $hints as $relationType => $hintUrls ) {
            /** @var array<array-key,array<string,string>|string> $hintUrls */
            $hintUrls = apply_filters( 'wp_resource_hints', $hintUrls, $relationType );

            foreach ( $hintUrls as $hintUrl ) {
                /** @var array<string,string> $atts */
                $atts = [];

                if ( \is_array( $hintUrl ) ) {
                    if ( ! isset( $hintUrl['href'] ) ) {
                        continue;
                    }

                    $atts = $hintUrl;
                    $hintUrl = $hintUrl['href'];
                }

                $hintUrl = esc_url( $hintUrl, [ 'https' ] );

                if ( empty( $hintUrl ) ) {
                    continue;
                }

                if ( ! filter_var( $hintUrl, FILTER_VALIDATE_URL ) ) {
                    continue;
                }

                if ( isset( $uniqueUrls[ $hintUrl ] ) ) {
                    continue;
                }

                $atts['href'] = $hintUrl;
                $atts['rel'] = $relationType;

                $uniqueUrls[ $hintUrl ] = $atts;
            }
        }

        return $uniqueUrls;
    }

    /**
     * @return array<string,array<string,string>|string[]>
     */
    private static function getResourceHints(): array {
        /** @var array<string,array<string,string>|string[]>|null $hints */
        static $hints;

        if ( null === $hints ) {
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
