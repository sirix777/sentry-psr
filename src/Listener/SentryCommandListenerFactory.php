<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Listener;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sentry\State\HubInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\Redaction\RedactorInterface;
use Sirix\SentryPsr\Config\SentryPsrConfig;
use Sirix\SentryPsr\Helper\LoggerHelper;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Redaction\SentryRedactorFactory;

class SentryCommandListenerFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): SentryCommandListener
    {
        $containerResolver = ContainerResolver::forFactory($container, self::class);
        $configReader      = ConfigReader::fromContainer($containerResolver);
        SentryPsrConfig::assertConfigured($configReader);

        $redactor = $containerResolver->has(RedactorInterface::class)
            ? $containerResolver->get(RedactorInterface::class)
            : (new SentryRedactorFactory())($container);

        return new SentryCommandListener(
            $containerResolver->get(HubInterface::class),
            isolateScope: $configReader->requiredBool('sentry_psr.isolate_console_scope'),
            flushOnTerminate: $configReader->requiredBool('sentry_psr.flush_on_console_terminate'),
            captureConsoleInput: $configReader->requiredBool('sentry_psr.capture_console_input'),
            logConsoleCommandStart: $configReader->requiredBool('sentry_psr.log_console_command_start'),
            logger: LoggerHelper::getLogger($containerResolver),
            sentryLifecycle: $containerResolver->get(SentryLifecycle::class),
            redactor: $redactor,
        );
    }
}
