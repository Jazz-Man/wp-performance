<?php

namespace JazzMan\Performance;

use JazzMan\Performance\Optimization\CleanUp;
use JazzMan\Performance\Optimization\DuplicatePost;
use JazzMan\Performance\Optimization\Enqueue;
use JazzMan\Performance\Optimization\Http;
use JazzMan\Performance\Optimization\LastPostModified;
use JazzMan\Performance\Optimization\Media;
use JazzMan\Performance\Optimization\NavMenuCache;
use JazzMan\Performance\Optimization\PostGuid;
use JazzMan\Performance\Optimization\PostMeta;
use JazzMan\Performance\Optimization\TermCount;
use JazzMan\Performance\Optimization\Update;
use JazzMan\Performance\Optimization\WPQuery;
use JazzMan\Performance\Security\ContactFormSpamTester;
use JazzMan\Performance\Security\SanitizeFileName;
use JazzMan\Performance\Utils\Cache;
use JazzMan\Performance\Utils\WPBlocks;
use JazzMan\Performance\Cli\FixPostGuidCommand;
use JazzMan\Performance\Cli\SanitizeFileNameCommand;

/**
 * Class App.
 */
class App
{
    public function __construct()
    {
        $classes = [
            Cache::class,
            NavMenuCache::class,
            WPBlocks::class,
            PostGuid::class,
            Http::class,
            Update::class,
            Media::class,
            WPQuery::class,
            PostMeta::class,
            LastPostModified::class,
            TermCount::class,
            SanitizeFileName::class,
            ContactFormSpamTester::class,
            CleanUp::class,
            Enqueue::class,
            DuplicatePost::class,
        ];

        if (app_is_wp_cli()) {
            $classes[] = SanitizeFileNameCommand::class;
            $classes[] = FixPostGuidCommand::class;
        }

        app_autoload_classes($classes);
    }

}
