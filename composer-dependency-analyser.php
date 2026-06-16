<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->ignoreErrorsOnPackageAndPaths(
        'symfony/console',
        [
            __DIR__ . '/src/Listener/SentryCommandListener.php',
        ],
        [ErrorType::DEV_DEPENDENCY_IN_PROD],
    )
    ->ignoreErrorsOnPackageAndPaths(
        'symfony/event-dispatcher',
        [
            __DIR__ . '/src/ConsoleEventDispatcher/ConsoleEventDispatcherFactory.php',
            __DIR__ . '/src/Listener/SentryCommandListener.php',
        ],
        [ErrorType::DEV_DEPENDENCY_IN_PROD],
    );
