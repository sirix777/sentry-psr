<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function(RectorConfig $rectorConfig): void {
    $rectorConfig->parallel(processTimeout: 360);

    $rectorConfig->paths([
        __DIR__ . '/src',
    ]);

    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::PRIVATIZATION,
        SetList::DEAD_CODE,
        LevelSetList::UP_TO_PHP_83,
    ]);

    $rectorConfig->skip([
        StringClassNameToClassConstantRector::class => [
            __DIR__ . '/src/ConfigProvider.php',
            __DIR__ . '/src/Helper/LoggerHelper.php',
        ],
    ]);
};
