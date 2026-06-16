<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\ConsoleEventDispatcher;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\SentryPsr\ConsoleEventDispatcher\ConsoleEventDispatcherFactory;
use Sirix\SentryPsr\Listener\SentryCommandListener;
use Sirix\SentryPsr\Test\Container\InMemoryContainer;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[CoversClass(ConsoleEventDispatcherFactory::class)]
final class ConsoleEventDispatcherFactoryTest extends TestCase
{
    private ConsoleEventDispatcherFactory $factory;

    public function setUp(): void
    {
        parent::setUp();

        $this->factory = new ConsoleEventDispatcherFactory();
    }

    public function testCreatesEventDispatcherWithSubscriber(): void
    {
        $listener = new class implements EventSubscriberInterface {
            public static function getSubscribedEvents(): array
            {
                return [
                    'console.error' => 'onError',
                ];
            }
        };

        $dispatcher = $this->factory->__invoke(new InMemoryContainer([
            SentryCommandListener::class => $listener,
        ]));

        $this->assertInstanceOf(EventDispatcher::class, $dispatcher);
        $this->assertTrue($dispatcher->hasListeners('console.error'));
    }

    public function testThrowsWhenListenerNotFound(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);

        $this->factory->__invoke(new InMemoryContainer());
    }
}
