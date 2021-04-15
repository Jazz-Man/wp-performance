<?php

namespace JazzMan\Performance\Utils;

use JazzMan\AutoloadInterface\AutoloadInterface;

class Cache implements AutoloadInterface
{
    public const CACHE_GROUP = 'wp-performance';
    public const QUERY_CACHE_GROUP = 'query';

    public function load()
    {
        // TODO: Implement load() method.
    }
}