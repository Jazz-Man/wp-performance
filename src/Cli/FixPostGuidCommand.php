<?php

namespace JazzMan\Performance\Cli;

use Exception;
use JazzMan\Performance\Optimization\PostGuid;
use WP_CLI;
use WP_Post;

class FixPostGuidCommand extends Command {
    /**
     * @param mixed $args
     * @param mixed $assocArgs
     */
    public function all(?array $args = null, ?array $assocArgs = null): void {
        $sites = $this->getAllSites();

        foreach ($sites as $siteId) {
            $this->maybeSwitchToBlog($siteId);

            global $wpdb;

            /** @noinspection SqlResolve */
            $postsIds = $wpdb->get_col("select p.ID from $wpdb->posts as p");

            if ( ! empty($postsIds)) {
                foreach ($postsIds as $postId) {
                    $post = get_post((int) $postId);

                    if ($post instanceof WP_Post) {
                        PostGuid::fixPostGuid($post->ID, $post);
                    }
                }
            }
        }
    }
}

try {
    WP_CLI::add_command('fixguid', FixPostGuidCommand::class);
} catch (Exception $exception) {
    app_error_log($exception, __FILE__);
}
