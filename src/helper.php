<?php

use JazzMan\Performance\Utils\AttachmentData;

if ( ! function_exists( 'app_get_image_data_array' ) ) {
    /**
     * @param int[]|string $size
     *
     * @return array{alt?: string, height: int, id?: int, size: array<array-key, int>|string, sizes?: string, srcset?: string, url: string, width: int}|false
     */
    function app_get_image_data_array( int|string $attachmentId, array|string $size = AttachmentData::SIZE_LARGE ): array|bool {
        $imageData = [
            'size' => $size,
        ];

        if ( is_array( $size ) ) {
            $image = wp_get_attachment_image_src( (int) $attachmentId, $size );

            if ( ! empty( $image ) ) {
                [ $url, $width, $height ] = $image;

                /** @var string|null $alt */
                $alt = get_post_meta( (int) $attachmentId, '_wp_attachment_image_alt', true );

                $imageData['url'] = $url;
                $imageData['width'] = $width;
                $imageData['height'] = $height;

                if ( ! empty( $alt ) ) {
                    $imageData['alt'] = $alt;
                }

                return $imageData;
            }

            return false;
        }

        try {
            $attachment = new AttachmentData( $attachmentId );

            $image = $attachment->getUrl( $size );

            $imageData['id'] = (int) $attachmentId;
            $imageData['url'] = (string) $image['src'];
            $imageData['width'] = (int) $image['width'];
            $imageData['height'] = (int) $image['height'];
            $imageData['srcset'] = (string) $image['srcset'];
            $imageData['sizes'] = (string) $image['sizes'];

            $imgAlt = $attachment->getImageAlt();

            if ( ! empty( $imgAlt ) ) {
                $imageData['alt'] = $imgAlt;
            }

            return $imageData;
        } catch ( Exception $exception ) {
            app_error_log( $exception, 'app_get_image_data_array' );

            return false;
        }
    }
}

if ( ! function_exists( 'app_get_attachment_image_url' ) ) {
    /**
     * @param int[]|string $size
     *
     * @return false|string
     */
    function app_get_attachment_image_url( int|string $attachmentId, array|string $size = AttachmentData::SIZE_THUMBNAIL ): bool|string {
        try {
            $image = app_get_image_data_array( $attachmentId, $size );

            if ( ! is_array( $image ) ) {
                return false;
            }

            if ( empty( $image['url'] ) ) {
                return false;
            }

            return $image['url'];
        } catch ( Exception ) {
            return false;
        }
    }
}

if ( ! function_exists( 'app_get_attachment_image' ) ) {
    /**
     * @param int[]|string                      $size
     * @param array<string,mixed>|object|string $attributes
     */
    function app_get_attachment_image( int|string $attachmentId, array|string $size = AttachmentData::SIZE_THUMBNAIL, array|object|string $attributes = '' ): string {
        /** @var array<string,int|string> $image */
        $image = app_get_image_data_array( $attachmentId, $size );

        if ( empty( $image ) ) {
            /** @var string $size */
            $exception = new Exception( sprintf( 'Image not fount: attachment_id "%d", size "%s"', $attachmentId, $size ) );
            app_error_log( $exception, 'app_get_attachment_image' );

            return '';
        }

        try {
            $lazyLoading = function_exists( 'wp_lazy_loading_enabled' ) && wp_lazy_loading_enabled( 'img', 'wp_get_attachment_image' );

            /** @var string $size */
            $defaultAttributes = [
                'src' => $image['url'],
                'class' => sprintf( 'attachment-%1$s size-%1$s', $size ),
                'alt' => empty( $image['alt'] ) ? false : app_trim_string( strip_tags( (string) $image['alt'] ) ),
                'width' => empty( $image['width'] ) ? false : (int) $image['width'],
                'height' => empty( $image['height'] ) ? false : (int) $image['height'],
                'loading' => $lazyLoading ? 'lazy' : false,
                'decoding' => 'async',
            ];

            /** @var array<string,bool|int|string|null> $attributes */
            $attributes = wp_parse_args( $attributes, $defaultAttributes );

            $attributes['srcset'] = empty( $attributes['srcset'] ) ? ( empty( $image['srcset'] ) ? false : $image['srcset'] ) : ( $attributes['srcset'] );
            $attributes['sizes'] = empty( $attributes['sizes'] ) ? ( empty( $image['sizes'] ) ? false : $image['sizes'] ) : ( $attributes['sizes'] );

            // If the default value of `lazy` for the `loading` attribute is overridden
            // to omit the attribute for this image, ensure it is not included.
            if ( array_key_exists( 'loading', $attributes ) && ! $attributes['loading'] ) {
                unset( $attributes['loading'] );
            }

            $post = get_post( (int) $attachmentId );

            /** @var array<string,string|string[]> $attributes */
            $attributes = apply_filters( 'wp_get_attachment_image_attributes', $attributes, $post, $size );

            return sprintf( '<img %s/>', app_add_attr_to_el( $attributes ) );
        } catch ( Exception $exception ) {
            app_error_log( $exception, __FUNCTION__ );

            return '';
        }
    }
}

if ( ! function_exists( 'app_attachment_url_to_postid' ) ) {
    /**
     * @return false|int
     */
    function app_attachment_url_to_postid( string $url ): bool|int {
        global $wpdb;

        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        $filetype = wp_check_filetype( $url );

        if ( empty( $filetype['ext'] ) ) {
            return false;
        }

        $relative_url = wp_make_link_relative( $url );

        $old_basename = basename( $url );

        /**
         * removed image size from name.
         */
        $new_basename = (string) preg_replace( "/-\\d+x\\d+\\.{$filetype['ext']}/", ".{$filetype['ext']}", $old_basename );

        if ( empty( $new_basename ) ) {
            return false;
        }

        $guid_url = str_replace( $old_basename, $new_basename, $relative_url );

        if ( empty( $guid_url ) ) {
            return false;
        }

        try {
            $pdo = app_db_pdo();

            $pdoStatement = $pdo->prepare(
                <<<SQL
                    select
                      i.ID
                    from {$wpdb->posts} as i
                    where
                      i.post_type = 'attachment'
                      and i.guid = :guid
                    SQL
            );

            $pdoStatement->execute( [
                'guid' => esc_url_raw( home_url( $guid_url ) ),
            ] );

            return (int) $pdoStatement->fetchColumn();

        } catch ( Exception $exception ) {
            return false;
        }
    }
}

if ( ! function_exists( 'app_make_link_relative' ) ) {
    function app_make_link_relative( string $link ): string {
        if ( app_is_current_host( $link ) ) {
            return wp_make_link_relative( $link );
        }

        return $link;
    }
}

if ( ! function_exists( 'app_is_wp_importing' ) ) {
    function app_is_wp_importing(): bool {
        return defined( 'WP_IMPORTING' ) && WP_IMPORTING;
    }
}

if ( ! function_exists( 'app_is_wp_cli' ) ) {
    function app_is_wp_cli(): bool {
        return defined( 'WP_CLI' ) && WP_CLI;
    }
}

if ( ! function_exists( 'app_is_enabled_wp_performance' ) ) {
    /**
     * Checks when plugin should be enabled. This offers nice compatibilty with wp-cli.
     */
    function app_is_enabled_wp_performance(): bool {
        static $enabled;

        if ( null === $enabled ) {
            $enabled = ! wp_doing_cron() && ! app_is_wp_cli() && ! app_is_wp_importing();
        }

        return (bool) $enabled;
    }
}
