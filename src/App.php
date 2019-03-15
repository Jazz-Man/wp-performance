<?php

namespace JazzMan\Performance;

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
        ];
    }

    /**
     * Checks when plugin should be enabled This offers nice compatibilty with wp-cli.
     */

    public static function enabled()
    {
        return ! (\defined('WP_CLI') and WP_CLI);
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
