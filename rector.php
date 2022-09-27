<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Php55\Rector\Class_\ClassConstantToSelfClassRector;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $config): void {
    // here we can define, what sets of rules will be applied
    // tip: use "SetList" class to autocomplete sets

    $config->import(SetList::CODE_QUALITY);
    $config->import(SetList::PHP_74);
    $config->import(SetList::TYPE_DECLARATION);
    $config->import(SetList::TYPE_DECLARATION_STRICT);
    $config->import(SetList::EARLY_RETURN);
    $config->import(SetList::NAMING);
    $config->import(SetList::CODING_STYLE);
    $config->import(LevelSetList::UP_TO_PHP_74);

    $config->fileExtensions(['php']);
    $config->phpVersion(PhpVersion::PHP_74);

    $config->importNames();
    $config->importShortClasses(false);
    $config->parallel();
    $config->cacheDirectory(__DIR__.'/cache/rector');

    $config->paths([
        __DIR__.'/src',
        __DIR__.'/wp-performance.php',
    ]);

    $config->skip(
        [
            // or fnmatch
            __DIR__.'/vendor',
            __DIR__.'/cache',
            CallableThisArrayToAnonymousFunctionRector::class,
            ClassConstantToSelfClassRector::class,
            RemoveExtraParametersRector::class,
            EncapsedStringsToSprintfRector::class,
        ]
    );

    $config->autoloadPaths([
        __DIR__.'/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php',
    ]);
};
