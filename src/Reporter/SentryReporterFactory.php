<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Reporter;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sentry\State\HubInterface;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\SentryPsr\ExceptionFilter\ExceptionFilterResolver;

final readonly class SentryReporterFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): SentryReporter
    {
        $containerResolver = ContainerResolver::forFactory($container, self::class);

        return new SentryReporter(
            $containerResolver->get(HubInterface::class),
            ExceptionFilterResolver::optionalFromContainer($containerResolver),
        );
    }
}
