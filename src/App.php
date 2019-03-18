<?php

namespace JazzMan\Performance;

use JazzMan\Performance\Shortcode\Shortcode;

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
     * App constructor.
     */
    public function __construct()
    {
        $this->config();
        $this->initAutoload();
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
            TermCount::class
        ];
    }

    /**
     * Checks when plugin should be enabled This offers nice compatibilty with wp-cli.
     */

    public static function enabled()
    {
        return ( ! (defined('DOING_CRON') && DOING_CRON) && ! (defined('WP_CLI') && WP_CLI) && ! (defined('WP_IMPORTING') && WP_IMPORTING));

    }

    private function initAutoload()
    {

        foreach ($this->class_autoload as $class) {
            try {
                $_class = new \ReflectionClass($class);
                if ($_class->implementsInterface(AutoloadInterface::class)) {
                    /** @var AutoloadInterface $instance */
                    $instance = $_class->newInstance();
                    $instance->load();
                }
            } catch (\ReflectionException $e) {
                wp_die($e, 'ReflectionException');
            }
        }
    }
}
