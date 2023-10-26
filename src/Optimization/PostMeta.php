<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;

/**
 * Class PostMeta.
 */
final class PostMeta implements AutoloadInterface {

    public function load(): void {
        // Disable custom fields meta box dropdown (very slow)
        add_filter( 'postmeta_form_keys', '__return_false' );
    }
}
