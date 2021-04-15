<?php

namespace JazzMan\Performance\Utils;

use JazzMan\AutoloadInterface\AutoloadInterface;

class Cache implements AutoloadInterface
{
    public const CACHE_GROUP = 'wp-performance';
    public const QUERY_CACHE_GROUP = 'query';

    public function load()
    {
        add_action('save_post_attachment', [$this, 'resetAttachmentCache'], 10, 2);
    }

    public function resetAttachmentCache(int $post_id, \WP_Post $post)
    {
        wp_cache_delete("attachment_image_{$post_id}", self::CACHE_GROUP);
    }
}