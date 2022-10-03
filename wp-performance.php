<?php

/**
 * Plugin Name:         wp-performance
 * Plugin URI:          https://github.com/Jazz-Man/wp-performance
 * Description:         The main task of this plugin is to increase the security of the site and improve the performance of the site by disabling completely unnecessary hooks and also optimizing SQL queries
 * Author:              Vasyl Sokolyk
 * Author URI:          https://www.linkedin.com/in/sokolyk-vasyl
 * Requires at least:   5.2
 * Requires PHP:        7.4
 * License:             MIT
 * Update URI:          https://github.com/Jazz-Man/wp-performance.
 */

use JazzMan\Performance\Optimization\CleanUp;
use JazzMan\Performance\Optimization\Enqueue;
use JazzMan\Performance\Optimization\LastPostModified;
use JazzMan\Performance\Optimization\Media;
use JazzMan\Performance\Optimization\PostGuid;
use JazzMan\Performance\Optimization\PostMeta;
use JazzMan\Performance\Optimization\TermCount;
use JazzMan\Performance\Optimization\Update;
use JazzMan\Performance\Optimization\WPQuery;
use JazzMan\Performance\Security\ContactFormSpamTester;
use JazzMan\Performance\Security\Sanitize;
use JazzMan\Performance\Utils\Cache;
use JazzMan\Performance\Utils\ResourceHints;

if (function_exists('app_autoload_classes')) {
    app_autoload_classes(
        [
            Cache::class,
            PostGuid::class,
            ResourceHints::class,
            Update::class,
            Media::class,
            WPQuery::class,
            PostMeta::class,
            LastPostModified::class,
            TermCount::class,
            Sanitize::class,
            ContactFormSpamTester::class,
            CleanUp::class,
            Enqueue::class,
        ]
    );
}
