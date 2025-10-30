<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Helper;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sentry\State\HubInterface;

final class SentryHelperFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): string
    {
        if ($container->has(HubInterface::class)) {
            SentryHelper::setHub($container->get(HubInterface::class));
        }

        return SentryHelper::class;
    }
}
