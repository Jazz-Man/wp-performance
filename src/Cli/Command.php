<?php

namespace JazzMan\Performance\Cli;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_CLI;
use WP_CLI_Command;

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
        return is_multisite() ? get_sites(['fields' => 'ids']) : (array) get_current_blog_id();
    }

    protected function maybeSwitchToBlog(int $siteId)
    {
        if (is_multisite()) {
            WP_CLI::line(sprintf('Processing network site: %d', esc_attr($siteId)));
            switch_to_blog($siteId);
        }
    }
}
