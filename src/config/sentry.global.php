<?php

declare(strict_types=1);

return [
    'sentry' => [
        'dsn'                => \getenv('SENTRY_DSN'),
        'traces_sample_rate' => 1.0,
    ],
];
