<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Middleware;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sentry\State\HubInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\SentryPsr\Config\SentryPsrConfig;
use Sirix\SentryPsr\Helper\LoggerHelper;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;

class SentryErrorMiddlewareFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): SentryErrorMiddleware
    {
        $containerResolver = ContainerResolver::forFactory($container, self::class);
        $configReader      = ConfigReader::fromContainer($containerResolver);
        SentryPsrConfig::assertConfigured($configReader);

        /** @var array<string, mixed> $httpContext */
        $httpContext = $configReader->requiredArray('sentry_psr.http_context');

        return new SentryErrorMiddleware(
            $containerResolver->get(HubInterface::class),
            isolateScope: $configReader->requiredBool('sentry_psr.isolate_http_scope'),
            flushOnHttpError: $configReader->requiredBool('sentry_psr.flush_on_http_error'),
            captureRequestContext: $configReader->requiredBool('sentry_psr.capture_http_request_context')
                && $configReader->requiredBool('sentry_psr.http_context.enabled'),
            httpContext: $httpContext,
            logger: LoggerHelper::getLogger($containerResolver),
            sentryLifecycle: $containerResolver->get(SentryLifecycle::class),
        );
    }
}
