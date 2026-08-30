<?php

declare(strict_types=1);

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;
use Override;

/**
 * Class PostMeta.
 */
final class PostMeta implements AutoloadInterface {

    #[Override]
    public function load(): void {
        // Disable custom fields meta box dropdown (very slow)
        add_filter( 'postmeta_form_keys', '__return_false' );
    }
}
