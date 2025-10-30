<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Hub;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;

use function array_key_exists;
use function Sentry\init;

class SentryHubFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): HubInterface
    {
        $config = $container->get('config')['sentry'] ?? [];

        // Avoid registering global error/exception handlers by default.
        // This keeps test environments and embedding applications in control.
        if (! array_key_exists('default_integrations', $config)) {
            $config['default_integrations'] = false;
        }

        init($config);

        return SentrySdk::getCurrentHub();
    }
}
