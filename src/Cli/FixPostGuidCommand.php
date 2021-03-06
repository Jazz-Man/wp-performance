<?php

namespace JazzMan\Performance\Cli;

use JazzMan\Performance\Optimization\PostGuid;
use WP_CLI;

class FixPostGuidCommand extends Command
{
    public function all($args, $assoc_args)
    {
        $sites = $this->getAllSites();

        foreach ($sites as $site_id) {
            $this->maybeSwitchToBlog($site_id);

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
