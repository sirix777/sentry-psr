<?php

declare(strict_types=1);

namespace Sirix\SentryPsr;

use Psr\EventDispatcher\EventDispatcherInterface;
use Sentry\State\HubInterface;
use Sirix\SentryPsr\ConsoleEventDispatcher\ConsoleEventDispatcherFactory;
use Sirix\SentryPsr\Helper\SentryHelper;
use Sirix\SentryPsr\Helper\SentryHelperFactory;
use Sirix\SentryPsr\Hub\SentryHubFactory;
use Sirix\SentryPsr\Listener\SentryCommandListener;
use Sirix\SentryPsr\Listener\SentryCommandListenerFactory;
use Sirix\SentryPsr\Middleware\SentryErrorMiddleware;
use Sirix\SentryPsr\Middleware\SentryErrorMiddlewareFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function class_exists;

class ConfigProvider
{
    /**
     * Returns the configuration array.
     *
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                'aliases' => $this->getAliases(),
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
            HubInterface::class => SentryHubFactory::class,
            SentryErrorMiddleware::class => SentryErrorMiddlewareFactory::class,
            SentryHelper::class => SentryHelperFactory::class,
        ];

        if (class_exists(Command::class)) {
            $factories[SentryCommandListener::class] = SentryCommandListenerFactory::class;
            $factories[EventDispatcher::class] = ConsoleEventDispatcherFactory::class;
        }

        return $factories;
    }

    /**
     * @return array<string, string>
     */
    protected function getAliases(): array
    {
        if (class_exists(Command::class)) {
            return [
                'Laminas\Cli\SymfonyEventDispatcher' => EventDispatcher::class,
                EventDispatcherInterface::class => EventDispatcher::class,
            ];
        }

        return [];
    }
}
