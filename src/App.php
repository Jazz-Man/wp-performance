<?php

namespace JazzMan\Performance;

use JazzMan\Performance\Shortcode\Shortcode;
use JazzMan\Performance\WP_CLI\Sanitize_Command;

/**
 * Class App.
 */
class App
{
    /**
     * App constructor.
     */
    public function __construct()
    {
        app_autoload_classes([
            Options::class,
            Update::class,
            Media::class,
            WP_Query_Performance::class,
            PostMeta::class,
            Shortcode::class,
            LastPostModified::class,
            BulkEdit::class,
            TermCount::class,
            Sanitizer::class,
            CleanUp::class,
        ]);

        if (self::isCli()) {
            app_autoload_classes([
                Sanitize_Command::class,
            ]);
        }
    }

    /**
     * @return bool
     */
    public static function isCli()
    {
        return \defined('WP_CLI') && WP_CLI;
    }

    /**
     * @return bool
     */
    public static function isCron()
    {
        return \defined('DOING_CRON') && DOING_CRON;
    }

    /**
     * @return bool
     */
    public static function isImporting()
    {
        return \defined('WP_IMPORTING') && WP_IMPORTING;
    }

    /**
     * Checks when plugin should be enabled This offers nice compatibilty with wp-cli.
     */
    public static function enabled()
    {
        return !self::isCron() && !self::isCli() && !self::isImporting();
    }
}
