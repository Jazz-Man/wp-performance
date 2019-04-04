<?php

use JazzMan\Performance\Shortcode\ShortcodeParser;
use JazzMan\Performance\Shortcode\ShortcodeRenderer;

if (!function_exists('app_do_shortcode')) {
    /**
     * @param string $content
     *
     * @return string
     */
    function app_do_shortcode($content)
    {
        $parser = new ShortcodeParser(false);
        $renderer = new ShortcodeRenderer();
        $doc_tree = $parser->parse($content);

        return $renderer->render($doc_tree);
    }
}
