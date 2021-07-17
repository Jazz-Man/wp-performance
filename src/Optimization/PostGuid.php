<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\App;
use WP_CLI;

class PostGuid implements AutoloadInterface
{
    public function load()
    {
        add_action('save_post', [__CLASS__, 'fixPostGuid'], 10, 2);
    }

    public static function fixPostGuid(int $postId, \WP_Post $post): void
    {
        if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        global $wpdb;

        $guid = 'attachment' === $post->post_type ?
            app_get_attachment_image_url($postId, 'full') :
            get_permalink($postId);

        if ( ! empty($guid)) {
            if (App::isCli()) {
                WP_CLI::line(
                    sprintf(
                        'Update guid "%s" for post_id "%d" and post_type "%s"',
                        esc_attr($guid),
                        esc_attr($postId),
                        esc_attr($post->post_type)
                    )
                );
            }

            $wpdb->update(
                $wpdb->posts,
                ['guid' => $guid],
                ['ID' => $postId]
            );

            clean_post_cache($postId);

            _prime_post_caches((array) $postId, 'attachment' !== $post->post_type);
        }
    }
}
