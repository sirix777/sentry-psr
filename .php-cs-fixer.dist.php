<?php

declare(strict_types=1);

use Sirix\CsFixerConfig\ConfigBuilder;


return ConfigBuilder::create()
    ->inDir(__DIR__ . '/src')
    ->inDir(__DIR__ . '/test')
    ->setRules([
        '@PHP8x2Migration' => true,
    ])
    ->getConfig()
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setUnsupportedPhpVersionAllowed(true)
;
