<?php

declare( strict_types=1 );

use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\Config\RectorConfig;
use Rector\Exception\Configuration\InvalidConfigurationException;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;

try {
    return RectorConfig::configure()
        ->withPreparedSets(
            codeQuality: true,
            codingStyle: true,
            typeDeclarations: true,
            privatization: true,
            naming: true,
            earlyReturn: true,
            rectorPreset: true
        )
        ->withPhpSets( php82: true )

        ->withFileExtensions( ['php'] )
        ->withImportNames( importShortClasses: false, removeUnusedImports: true )
        ->withParallel(  )
        ->withPHPStanConfigs( [
            __DIR__.'/phpstan-rector.neon',
        ] )
        ->withPaths( [
            __DIR__.'/src',
            __DIR__.'/wp-performance.php',
        ] )
        ->withSkip( [
            __DIR__.'/vendor',
            __DIR__.'/cache',
            RemoveExtraParametersRector::class,
            EncapsedStringsToSprintfRector::class,
            DisallowedEmptyRuleFixerRector::class,
            SimplifyEmptyCheckOnEmptyArrayRector::class,
        ] )
    ;
} catch ( InvalidConfigurationException $e ) {
    var_dump( $e->getMessage() );

}
