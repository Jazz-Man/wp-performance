<?php

namespace JazzMan\Performance\Utils;

use JazzMan\AutoloadInterface\AutoloadInterface;

final class Cache implements AutoloadInterface {

    public const CACHE_GROUP = 'wp-performance';

    /**
     * @var string
     */
    public const QUERY_CACHE_GROUP = 'query';

    public function load(): void {
        add_action( 'save_post_attachment', self::resetAttachmentCache( ... ) );
    }

    public static function resetAttachmentCache( int $postId ): void {
        wp_cache_delete( \sprintf( 'attachment_image_%d', $postId ), self::CACHE_GROUP );
    }
}
