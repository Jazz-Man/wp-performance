<?php

namespace JazzMan\Performance\Security;

use JazzMan\AutoloadInterface\AutoloadInterface;

/**
 * Class Sanitizer.
 */
final class Sanitize implements AutoloadInterface {

    public function load(): void {

        // Edit and update terms
        add_filter( 'edit_term_slug', 'app_string_slugify' );
        add_filter( 'pre_term_slug', 'app_string_slugify' );
        add_filter( 'term_slug_rss', 'app_string_slugify' );

        // Edit and update post
        add_filter( 'pre_post_name', 'app_string_slugify' );
        add_filter( 'edit_post_name', 'app_string_slugify' );

        add_filter( 'sanitize_html_class', 'app_string_slugify' );

        add_filter( 'sanitize_email', self::sanitizeEmail( ... ) );

        // Remove accents from all uploaded files
        add_filter( 'sanitize_file_name', self::sanitizeFilename( ... ) );
    }

    /**
     * Removes all accents from string.
     *
     * @param string $fileName - any filename with absolute path
     */
    public static function sanitizeFilename( string $fileName ): string {
        // Get path and basename
        $fileInfo = pathinfo( $fileName );

        $fileExt = empty( $fileInfo['extension'] ) ? '' : '.'.strtolower( $fileInfo['extension'] );

        // Trim beginning and ending seperators
        $fileName = app_string_slugify( $fileInfo['filename'] ).$fileExt;

        if ( ! empty( $fileInfo['dirname'] ) && '.' !== $fileInfo['dirname'] ) {
            return $fileInfo['dirname'].'/'.$fileName;
        }

        // Return full path
        return $fileName;
    }

    public static function sanitizeEmail( string $sanitizedEmail ): string {
        if ( ! empty( $sanitizedEmail ) && filter_var( $sanitizedEmail, FILTER_VALIDATE_EMAIL ) ) {
            /** @var false|string $email */
            $email = filter_var( $sanitizedEmail, FILTER_SANITIZE_EMAIL );

            return empty( $email ) ? $sanitizedEmail : $email;
        }

        return $sanitizedEmail;
    }
}
