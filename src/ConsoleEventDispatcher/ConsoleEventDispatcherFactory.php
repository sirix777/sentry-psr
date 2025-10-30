<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\ConsoleEventDispatcher;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\SentryPsr\Listener\SentryCommandListener;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ConsoleEventDispatcherFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): EventDispatcher
    {
        $dispatcher = new EventDispatcher();

        $sentryListener = $container->get(SentryCommandListener::class);
        $dispatcher->addSubscriber($sentryListener);

        return $dispatcher;
    }
}
