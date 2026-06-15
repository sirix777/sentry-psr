<?php

declare(strict_types=1);

namespace Sirix\SentryPsr;

use Sentry\State\HubInterface;
use Sirix\SentryPsr\ConsoleEventDispatcher\ConsoleEventDispatcherFactory;
use Sirix\SentryPsr\Hub\SentryHubFactory;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Lifecycle\SentryLifecycleFactory;
use Sirix\SentryPsr\Listener\SentryCommandListener;
use Sirix\SentryPsr\Listener\SentryCommandListenerFactory;
use Sirix\SentryPsr\Middleware\SentryErrorMiddleware;
use Sirix\SentryPsr\Middleware\SentryErrorMiddlewareFactory;
use Sirix\SentryPsr\Reporter\SentryReporter;
use Sirix\SentryPsr\Reporter\SentryReporterFactory;

use function class_exists;
use function interface_exists;

class ConfigProvider
{
    /**
     * Returns the configuration array.
     *
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     *
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                'aliases'   => $this->getAliases(),
                'factories' => $this->getFactories(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getFactories(): array
    {
        $factories = [
            HubInterface::class          => SentryHubFactory::class,
            SentryLifecycle::class       => SentryLifecycleFactory::class,
            SentryReporter::class        => SentryReporterFactory::class,
            SentryErrorMiddleware::class => SentryErrorMiddlewareFactory::class,
        ];

        if ($this->hasConsoleIntegration()) {
            $factories[SentryCommandListener::class]                        = SentryCommandListenerFactory::class;
            $factories['Symfony\Component\EventDispatcher\EventDispatcher'] = ConsoleEventDispatcherFactory::class;
        }

        return $factories;
    }

    /**
     * @return array<string, string>
     */
    protected function getAliases(): array
    {
        if (! $this->hasConsoleIntegration()) {
            return [];
        }

        return [
            'Laminas\Cli\SymfonyEventDispatcher'           => 'Symfony\Component\EventDispatcher\EventDispatcher',
            'Psr\EventDispatcher\EventDispatcherInterface' => 'Symfony\Component\EventDispatcher\EventDispatcher',
        ];
    }

    protected function hasConsoleIntegration(): bool
    {
        return class_exists('Symfony\Component\Console\Command\Command')
            && class_exists('Symfony\Component\Console\ConsoleEvents')
            && interface_exists('Symfony\Component\EventDispatcher\EventSubscriberInterface')
            && class_exists('Symfony\Component\EventDispatcher\EventDispatcher');
    }
}
