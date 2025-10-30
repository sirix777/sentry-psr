<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Middleware;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sentry\State\HubInterface;
use Sirix\SentryPsr\Helper\LoggerHelper;

class SentryErrorMiddlewareFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): SentryErrorMiddleware
    {
        $sentryHub = $container->get(HubInterface::class);

        $logger = LoggerHelper::getLogger($container);

        return new SentryErrorMiddleware(
            $sentryHub,
            $logger
        );
    }
}
