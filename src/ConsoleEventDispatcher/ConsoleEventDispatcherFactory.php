<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\ConsoleEventDispatcher;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\SentryPsr\Listener\SentryCommandListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ConsoleEventDispatcherFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): EventDispatcher
    {
        $containerResolver   = ContainerResolver::forFactory($container, self::class);
        $eventDispatcher     = new EventDispatcher();

        $eventDispatcher->addSubscriber($containerResolver->getAs(SentryCommandListener::class, EventSubscriberInterface::class));

        return $eventDispatcher;
    }
}
