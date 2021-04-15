<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\App;

class PostGuid implements AutoloadInterface
{

    public function load()
    {
        add_action('save_post', [__CLASS__, 'fixPostGuid'], 10, 2);
    }

    /**
     * @param  int  $post_id
     * @param  \WP_Post  $post
     *
     * @return void
     */

    public static function fixPostGuid(int $post_id, \WP_Post $post): void
    {
        if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        global $wpdb;

        if ('attachment' === $post->post_type) {
            $guid = wp_get_attachment_image_url($post_id, 'full');
        } else {
            $guid = get_permalink($post_id);
        }

        if (!empty($guid)) {

            if (App::isCli()){
                WP_CLI::line(
                    sprintf(
                        'Update guid "%s" for post_id "%d" and post_type "%s"',
                        esc_attr($guid),
                        esc_attr($post_id),
                        esc_attr($post->post_type)
                    )
                );
            }


            $wpdb->update(
                $wpdb->posts,
                ['guid' => $guid],
                ['ID' => $post_id]
            );

            clean_post_cache($post_id);

            _prime_post_caches((array) $post_id, 'attachment' !== $post->post_type);
        }
    }
}