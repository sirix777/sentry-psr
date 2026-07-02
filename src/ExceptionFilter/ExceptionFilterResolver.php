<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\ExceptionFilter;

use Psr\Container\ContainerExceptionInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\SentryPsr\Config\SentryPsrConfig;

final readonly class ExceptionFilterResolver
{
    /**
     * @throws ContainerExceptionInterface
     */
    public static function fromConfiguredContainer(ContainerResolver $containerResolver, ConfigReader $configReader): ExceptionFilterInterface
    {
        if ($containerResolver->has(ExceptionFilterInterface::class)) {
            return $containerResolver->getAs(ExceptionFilterInterface::class, ExceptionFilterInterface::class);
        }

        return ConfiguredExceptionFilterFactory::fromConfigReader($configReader);
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public static function optionalFromContainer(ContainerResolver $containerResolver): ExceptionFilterInterface
    {
        if ($containerResolver->has(ExceptionFilterInterface::class)) {
            return $containerResolver->getAs(ExceptionFilterInterface::class, ExceptionFilterInterface::class);
        }

        $configReader = ConfigReader::fromContainer($containerResolver);
        if ($configReader->has('sentry_psr')) {
            SentryPsrConfig::assertConfigured($configReader);

            return ConfiguredExceptionFilterFactory::fromConfigReader($configReader);
        }

        return ConfiguredExceptionFilter::allowAll();
    }
}
