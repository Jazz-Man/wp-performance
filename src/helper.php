<?php

use JazzMan\Performance\AutoloadInterface;

if (!function_exists('app_autoload_classes')) {
    /**
     * @param array $classes
     */
    function app_autoload_classes(array $classes)
    {
        foreach ($classes as $class) {
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
