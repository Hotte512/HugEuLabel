<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/src/Resources',
        __DIR__ . '/tests/bootstrap.php',
        // Produces inline fully-qualified instanceof checks where `=== null`
        // is clearer.
        FlipTypeControlToUseExclusiveTypeRector::class,
    ])
    ->withPhpSets(php82: true)
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::TYPE_DECLARATION,
    ]);
