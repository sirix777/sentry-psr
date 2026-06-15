<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Hub;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sentry\ClientBuilder;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\SentryPsr\Config\SentryPsrConfig;

use function array_key_exists;

class SentryHubFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): HubInterface
    {
        $containerResolver = ContainerResolver::forFactory($container, self::class);
        $configReader      = ConfigReader::fromContainer($containerResolver);
        SentryPsrConfig::assertConfigured($configReader);

        /** @var array<string, mixed> $sentryConfig */
        $sentryConfig        = $configReader->array('sentry', []);
        $defaultIntegrations = $configReader->requiredBool('sentry_psr.default_integrations');
        $setCurrentHub       = $configReader->requiredBool('sentry_psr.set_current_hub');

        if (! array_key_exists('default_integrations', $sentryConfig)) {
            $sentryConfig['default_integrations'] = $defaultIntegrations;
        }

        $hub = new Hub(ClientBuilder::create($sentryConfig)->getClient());

        if ($setCurrentHub) {
            SentrySdk::setCurrentHub($hub);
        }

        return $hub;
    }
}
