<?php

namespace JazzMan\Performance\WP_CLI;

use JazzMan\AutoloadInterface\AutoloadInterface;

abstract class Command extends WP_CLI_Command implements AutoloadInterface
{
    public function load()
    {
        // TODO: Implement load() method.
    }

    /**
     * @return int[]
     */
    protected function getAllSites(): array
    {
        if (is_multisite()) {
            $sites = get_sites([
                'fields' => 'ids',
            ]);
        } else {
            $sites = [get_current_blog_id()];
        }

        return $sites;
    }

    protected function maybeSwitchToBlog(int $site_id){
        if (is_multisite()) {
            WP_CLI::line(sprintf('Processing network site: %d', esc_attr($site_id)));
            switch_to_blog($site_id);
        }
    }
}
