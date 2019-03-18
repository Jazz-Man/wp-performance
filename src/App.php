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
     * @var array
     */
    private $class_autoload;

    /**
     * @var array
     */
    private $class_autoload_cli;

    /**
     * App constructor.
     */
    public function __construct()
    {
        $this->config();

        app_autoload_classes($this->class_autoload);

        if (self::is_cli()) {
            app_autoload_classes($this->class_autoload_cli);
        }
    }

    private function config()
    {
        $this->class_autoload = [
            Update::class,
            Media::class,
            WP_Query_Performance::class,
            PostMeta::class,
            Shortcode::class,
            LastPostModified::class,
            BulkEdit::class,
            TermCount::class,
            Sanitizer::class,
        ];

        $this->class_autoload_cli = [
            Sanitize_Command::class,
        ];
    }

    /**
     * @return bool
     */
    public static function is_cli()
    {
        return \defined('WP_CLI') && WP_CLI;
    }

    /**
     * Checks when plugin should be enabled This offers nice compatibilty with wp-cli.
     */
    public static function enabled()
    {
        return  !(\defined('DOING_CRON') && DOING_CRON) && !self::is_cli() && !(\defined('WP_IMPORTING') && WP_IMPORTING);
    }

}
