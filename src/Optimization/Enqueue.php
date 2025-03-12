<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_Site;

/**
 * Class Enqueue.
 */
final class Enqueue implements AutoloadInterface {

    public function load(): void {
        add_action( 'wp_enqueue_scripts', self::jsToFooter( ... ) );
        add_action( 'wp_enqueue_scripts', self::jqueryFromCdn( ... ) );
        add_filter( 'style_loader_tag', self::addAsyncStyle( ... ), 10, 4 );

        if ( ! is_admin() ) {
            add_filter( 'script_loader_src', self::setScriptVersion( ... ), 15, 2 );
            add_filter( 'style_loader_src', self::setScriptVersion( ... ), 15, 2 );
        }
    }

    public static function addAsyncStyle( string $tag, string $handle, string $href, string $media ): string {
        if ( 'print' === $media ) {
            return \sprintf(
                '<link rel="%s" id="%s-css" href="%s" media="%s" onload="this.media=\'all\'; this.onload=null;" />',
                'stylesheet',
                $handle,
                $href,
                $media
            );
        }

        return $tag;
    }

    public static function setScriptVersion( string $scriptSrc, string $handle ): string {
        if ( ! filter_var( $scriptSrc, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED ) ) {
            return $scriptSrc;
        }

        $addVersion = (bool) apply_filters( 'enqueue_add_script_version', true, $handle );

        if ( ! $addVersion ) {
            return $scriptSrc;
        }

        if ( ! app_is_current_host( $scriptSrc ) ) {
            return $scriptSrc;
        }

        $file = self::prepareScriptFilePath( $scriptSrc );

        if ( empty( $file ) ) {
            return $scriptSrc;
        }

        $fileMap = $file.'.map';

        $filePath = is_readable( $fileMap ) ? $fileMap : $file;

        return add_query_arg( [
            'ver' => hash_file( 'sha256', (string) $filePath ),
        ], $scriptSrc );
    }

    public static function jqueryFromCdn(): void {
        $registered = wp_scripts()->registered;

        /** @psalm-suppress TypeDoesNotContainType */
        $suffix = SCRIPT_DEBUG ? '' : '.min';

        $jqCore = $registered['jquery-core'];

        $jqUiCore = $registered['jquery-ui-core'];
        $jqMigrate = $registered['jquery-migrate'];

        $jqVer = trim( (string) $jqCore->ver, '-wp' );
        $jqUiVer = trim( (string) $jqUiCore->ver, '-wp' );
        $jqMigrateVer = trim( (string) $jqMigrate->ver, '-wp' );

        foreach ( $registered as $handle => $dependency ) {

            if ( empty( $dependency->src ) ) {
                continue;
            }

            /** @psalm-suppress UndefinedConstant */
            if ( ! str_contains( $dependency->src, '/'.WPINC.'/' ) ) {
                continue;
            }

            $isJqueryUiCore = str_starts_with( (string) $handle, 'jquery-effects-' ) || str_starts_with( (string) $handle, 'jquery-ui-' );

            if ( ! $isJqueryUiCore ) {
                continue;
            }

            $dependency->src = false;
        }

        self::deregisterScript( $jqCore->handle, \sprintf( 'https://cdn.jsdelivr.net/npm/jquery@%s/dist/jquery%s.js', esc_attr( $jqVer ), $suffix ) );
        self::deregisterScript( $jqMigrate->handle, \sprintf( 'https://cdn.jsdelivr.net/npm/jquery-migrate@%s/dist/jquery-migrate%s.js', esc_attr( $jqMigrateVer ), $suffix ) );
        self::deregisterScript( $jqUiCore->handle, \sprintf( 'https://cdn.jsdelivr.net/npm/jquery-ui-dist@%s/jquery-ui%s.js', esc_attr( $jqUiVer ), $suffix ) );

    }

    public static function deregisterScript( string $handle, ?string $newUrl = null, bool $enqueue = false ): void {
        $registered = wp_scripts()->registered;

        if ( ! empty( $registered[ $handle ] ) ) {
            $jsLib = $registered[ $handle ];

            wp_dequeue_script( $jsLib->handle );
            wp_deregister_script( $jsLib->handle );

            if ( null !== $newUrl ) {
                /** @var callable $function */
                $function = $enqueue ? 'wp_enqueue_script' : 'wp_register_script';

                $function( $jsLib->handle, $newUrl, $jsLib->deps, $jsLib->ver, true );
            }
        }
    }

    public static function deregisterStyle( string $handle, ?string $newUrl = null, bool $enqueue = false ): void {
        $registered = wp_styles()->registered;

        if ( ! empty( $registered[ $handle ] ) ) {
            $cssLib = $registered[ $handle ];

            wp_dequeue_style( $cssLib->handle );
            wp_deregister_style( $cssLib->handle );

            if ( null !== $newUrl ) {
                /** @var callable $function */
                $function = $enqueue ? 'wp_enqueue_style' : 'wp_register_style';
                $function( $cssLib->handle, $newUrl, $cssLib->deps, $cssLib->ver );
            }
        }
    }

    private static function jsToFooter(): void {
        remove_action( 'wp_head', 'wp_print_scripts' );
        remove_action( 'wp_head', 'wp_print_head_scripts', 9 );
        remove_action( 'wp_head', 'wp_enqueue_scripts', 1 );
    }

    private static function prepareScriptFilePath( string $scriptSrc ): bool|string {
        $rootDir = app_locate_root_dir();

        if ( empty( $rootDir ) ) {
            return false;
        }

        $path = ltrim( (string) parse_url( $scriptSrc, PHP_URL_PATH ), '/' );

        $file = \sprintf( '%s/%s', $rootDir, self::fixMultiSitePath( $path ) );

        return is_readable( $file ) ? $file : false;
    }

    private static function fixMultiSitePath( string $path ): string {
        static $blogDetails;

        if ( ! is_multisite() ) {
            return $path;
        }

        if ( null === $blogDetails ) {
            $blogDetails = get_blog_details( null, false );
        }

        if ( ! $blogDetails instanceof WP_Site ) {
            return $path;
        }

        return ltrim( $path, $blogDetails->path );
    }
}
