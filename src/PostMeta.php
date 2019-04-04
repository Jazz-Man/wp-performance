<?php

namespace JazzMan\Performance;

use JazzMan\AutoloadInterface\AutoloadInterface;

/**
 * Class PostMeta.
 */
class PostMeta implements AutoloadInterface
{
    public function load()
    {
        // Disable custom fields meta box dropdown (very slow)
        add_filter( 'postmeta_form_keys', '__return_false' );
    }
}
