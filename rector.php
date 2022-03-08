<?php

declare(strict_types=1);

use Rector\Core\Configuration\Option;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Set\ValueObject\SetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    // here we can define, what sets of rules will be applied
    // tip: use "SetList" class to autocomplete sets

    $containerConfigurator->import(SetList::CODE_QUALITY);
    $containerConfigurator->import(SetList::PHP_74);
    $containerConfigurator->import(SetList::TYPE_DECLARATION);
    $containerConfigurator->import(SetList::TYPE_DECLARATION_STRICT);
    $containerConfigurator->import(SetList::EARLY_RETURN);
    $containerConfigurator->import(SetList::NAMING);
    $containerConfigurator->import(SetList::CODING_STYLE);

    $parameters = $containerConfigurator->parameters();

    $parameters->set(Option::FILE_EXTENSIONS, ['php']);
    $parameters->set(Option::PHP_VERSION_FEATURES, PhpVersion::PHP_74);

    $parameters->set(Option::AUTO_IMPORT_NAMES, true);
    $parameters->set(Option::IMPORT_SHORT_CLASSES, false);
    $parameters->set(Option::CACHE_DIR, __DIR__ . '/cache/rector');

    // Path to phpstan with extensions, that PHPSTan in Rector uses to determine types
    //	$parameters->set(Option::PHPSTAN_FOR_RECTOR_PATH, getcwd() . '/phpstan.neon.dist');

    $parameters->set(Option::PATHS, [
        __DIR__ . '/src',
        __DIR__ . '/wp-performance.php',
    ]);

    $parameters->set(Option::SKIP, [
        // or fnmatch
        __DIR__ . '/vendor',
        __DIR__ . '/cache',
    ]);

    $parameters->set(
        Option::AUTOLOAD_PATHS,
        [
            __DIR__ . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php',
        ]
    );
};
