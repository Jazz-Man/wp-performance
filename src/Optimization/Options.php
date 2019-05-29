<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;

/**
 * Class Options
 *
 * @package JazzMan\Performance
 */
class Options implements AutoloadInterface
{
    public function load()
    {
        /*
         * Fix a race condition in alloptions caching
         *
         * @see https://core.trac.wordpress.org/ticket/31245
         */
        add_action('added_option', [$this, 'updateOption']);
        add_action('updated_option', [$this, 'updateOption']);
        add_action('deleted_option', [$this, 'updateOption']);
    }

    /**
     * @param $option
     */
    public function updateOption($option)
    {
        if (!wp_installing()) {
            $alloptions = wp_load_alloptions(); //alloptions should be cached at this point
            if (isset($alloptions[$option])) { //only if option is among alloptions
                wp_cache_delete('alloptions', 'options');
            }
        }
    }
}
