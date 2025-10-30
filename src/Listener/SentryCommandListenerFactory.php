<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Listener;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sentry\State\HubInterface;
use Sirix\SentryPsr\Helper\LoggerHelper;

class SentryCommandListenerFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): SentryCommandListener
    {
        $hub = $container->get(HubInterface::class);

        $logger = LoggerHelper::getLogger($container);

        return new SentryCommandListener(
            $hub,
            $logger,
        );
    }
}
