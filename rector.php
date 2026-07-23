<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/bin',
        __DIR__.'/config',
        __DIR__.'/public',
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/castor.php',
    ])
    ->withSkipPath(__DIR__.'/config/bundles.php')
    ->withSkipPath(__DIR__.'/config/reference.php')
    ->withPhpVersion(Rector\ValueObject\PhpVersion::PHP_84)
    ->withImportNames(removeUnusedImports: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        naming: true,
        instanceOf: true,
        earlyReturn: true,
        phpunitCodeQuality: true,
        doctrineCodeQuality: true,
    )
    ->withComposerBased(twig: true, doctrine: true, phpunit: true)
    ->withCache(
        cacheDirectory: __DIR__.'/var/rector',
        cacheClass: FileCacheStorage::class,
    )
;
