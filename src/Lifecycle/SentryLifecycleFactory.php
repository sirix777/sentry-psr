<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Lifecycle;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sentry\State\HubInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\SentryPsr\Config\SentryPsrConfig;
use Sirix\SentryPsr\Helper\LoggerHelper;

final readonly class SentryLifecycleFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): SentryLifecycle
    {
        $containerResolver = ContainerResolver::forFactory($container, self::class);
        $configReader      = ConfigReader::fromContainer($containerResolver);
        SentryPsrConfig::assertConfigured($configReader);

        return new SentryLifecycle(
            $containerResolver->get(HubInterface::class),
            flushTimeout: $configReader->requiredInt('sentry_psr.flush_timeout'),
            logger: LoggerHelper::getLogger($containerResolver),
        );
    }
}
