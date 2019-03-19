<?php

use JazzMan\Performance\AutoloadInterface;
use JazzMan\Performance\Shortcode\ShortcodeParser;
use JazzMan\Performance\Shortcode\ShortcodeRenderer;

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

if (!function_exists('add_do_shortcode')){
    /**
     * @param string $content
     *
     * @return string
     */
    function add_do_shortcode($content){
        $parser = new ShortcodeParser(false);
        $renderer = new ShortcodeRenderer();
        $doc_tree = $parser->parse($content);

        return $renderer->render($doc_tree);
    }
}
