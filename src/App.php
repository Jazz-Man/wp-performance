<?php

namespace JazzMan\Performance;

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
        $this->config();
        $this->initAutoload();
    }

    private function config()
    {
        add_filter('wp_app_config', function ($config) {
            $config['class_autoload'] = [
                Update::class,
            ];

            return $config;
        });
    }

    private function initAutoload()
    {
        if ($autoload = app_config()->get('class_autoload', false)) {
            foreach ($autoload as $class) {
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
}
