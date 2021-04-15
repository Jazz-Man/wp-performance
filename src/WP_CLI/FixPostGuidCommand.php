<?php

namespace JazzMan\Performance\WP_CLI;

use JazzMan\AutoloadInterface\AutoloadInterface;
use JazzMan\Performance\Optimization\PostGuid;

class FixPostGuidCommand extends WP_CLI_Command implements AutoloadInterface
{
    public function load()
    {
        // TODO: Implement load() method.
    }

    public function all($args, $assoc_args)
    {
        if (is_multisite()) {
            $sites = get_sites([
                'fields' => 'ids',
            ]);
        } else {
            $sites = [get_current_blog_id()];
        }

        foreach ($sites as $site_id) {
            if (is_multisite()) {
                WP_CLI::line(sprintf('Processing network site: %d', esc_attr($site_id)));
                switch_to_blog($site_id);
            }

            global $wpdb;

            $posts_ids = $wpdb->get_col("select p.ID from {$wpdb->posts} as p");

            if (!empty($posts_ids)) {
                foreach ($posts_ids as $id) {
                    $post = get_post((int) $id);
                    if ($post instanceof \WP_Post) {
                        PostGuid::fixPostGuid($post->ID, $post);
                    }
                }
            }
        }
    }
}

WP_CLI::add_command('fixguid', FixPostGuidCommand::class);
