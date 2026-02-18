<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use RectorLaravel\Rector\Empty_\EmptyToBlankAndFilledFuncRector;
use RectorLaravel\Rector\FuncCall\ConfigToTypedConfigMethodCallRector;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;
use RectorLaravel\Set\LaravelSetProvider;
use RectorPest\Set\PestLevelSetList;
use RectorPest\Set\PestSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/config',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/vendor',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    ->withPhpSets(php84: true)
    ->withParallel()
    ->withRules([
        EmptyToBlankAndFilledFuncRector::class,
        ConfigToTypedConfigMethodCallRector::class,
        AddOverrideAttributeToOverriddenMethodsRector::class,
    ])
    ->withSets([
        LaravelSetList::LARAVEL_ARRAYACCESS_TO_METHOD_CALL,
        LaravelSetList::LARAVEL_ARRAY_STR_FUNCTION_TO_STATIC_CALL,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_ELOQUENT_MAGIC_METHOD_TO_QUERY_BUILDER,
        LaravelSetList::LARAVEL_FACTORIES,
        LaravelSetList::LARAVEL_IF_HELPERS,
        LaravelLevelSetList::UP_TO_LARAVEL_120,
        LaravelSetList::LARAVEL_120,
        LaravelSetList::LARAVEL_TESTING,
        PestSetList::PEST_CODE_QUALITY,
        PestLevelSetList::UP_TO_PEST_40,
    ])
    ->withSetProviders(LaravelSetProvider::class)
    ->withComposerBased(laravel: true)
    ->withCache(__DIR__.'/.cache/rector', FileCacheStorage::class)
    ->withImportNames(importDocBlockNames: false, removeUnusedImports: true);
