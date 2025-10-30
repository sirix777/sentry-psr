<?php

declare(strict_types=1);

use Sirix\CsFixerConfig\ConfigBuilder;


return ConfigBuilder::create()
    ->inDir(__DIR__ . '/src')
    ->inDir(__DIR__ . '/test')
    ->setRules([
        '@PHP8x1Migration' => true,
        'Gordinskiy/line_length_limit' => ['max_length' => 140],
    ])
    ->getConfig()
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());
