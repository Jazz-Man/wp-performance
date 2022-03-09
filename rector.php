<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\Core\Configuration\Option;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Php55\Rector\Class_\ClassConstantToSelfClassRector;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    // here we can define, what sets of rules will be applied
    // tip: use "SetList" class to autocomplete sets

    $configurator->import(SetList::CODE_QUALITY);
    $configurator->import(SetList::PHP_74);
    $configurator->import(SetList::TYPE_DECLARATION);
    $configurator->import(SetList::TYPE_DECLARATION_STRICT);
    $configurator->import(SetList::EARLY_RETURN);
    $configurator->import(SetList::NAMING);
    $configurator->import(SetList::CODING_STYLE);
    $configurator->import(LevelSetList::UP_TO_PHP_74);

    $parameters = $configurator->parameters();

    $parameters->set(Option::FILE_EXTENSIONS, ['php']);
    $parameters->set(Option::PHP_VERSION_FEATURES, PhpVersion::PHP_74);

    $parameters->set(Option::AUTO_IMPORT_NAMES, true);
    $parameters->set(Option::IMPORT_SHORT_CLASSES, false);
    $parameters->set(Option::PARALLEL, true);
    $parameters->set(Option::CACHE_DIR, __DIR__ . '/cache/rector');

    $parameters->set(Option::PATHS, [
        __DIR__ . '/src',
        __DIR__ . '/wp-performance.php',
    ]);

    $parameters->set(Option::SKIP, [
        // or fnmatch
        __DIR__ . '/vendor',
        __DIR__ . '/cache',
        CallableThisArrayToAnonymousFunctionRector::class,
        ClassConstantToSelfClassRector::class,
        RemoveExtraParametersRector::class,
	    EncapsedStringsToSprintfRector::class,
    ]);

    $parameters->set(
        Option::AUTOLOAD_PATHS,
        [
            __DIR__ . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php',
        ]
    );
};
