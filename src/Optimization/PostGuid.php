<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_Post;
use wpdb;

final class PostGuid implements AutoloadInterface {

    public function load(): void {
        add_action( 'save_post', self::fixPostGuid( ... ), 10, 2 );
    }

    public static function fixPostGuid( int $postId, WP_Post $wpPost ): void {
        /** @var wpdb $wpdb */
        global $wpdb;

        if ( \defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $guid = 'attachment' === $wpPost->post_type
            ? app_get_attachment_image_url( $postId, 'full' )
            : get_permalink( $postId );

        if ( ! empty( $guid ) ) {
            $wpdb->update(
                $wpdb->posts,
                ['guid' => $guid],
                ['ID' => $postId]
            );

            clean_post_cache( $postId );

            _prime_post_caches( (array) $postId, 'attachment' !== $wpPost->post_type );
        }
    }
}
