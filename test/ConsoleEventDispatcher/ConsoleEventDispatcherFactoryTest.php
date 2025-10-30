<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\ConsoleEventDispatcher;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\SentryPsr\ConsoleEventDispatcher\ConsoleEventDispatcherFactory;
use Sirix\SentryPsr\Listener\SentryCommandListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[CoversClass(ConsoleEventDispatcherFactory::class)]
final class ConsoleEventDispatcherFactoryTest extends TestCase
{
    private ContainerInterface $containerMock;
    private ConsoleEventDispatcherFactory $factory;

    public function setUp(): void
    {
        parent::setUp();

        $this->containerMock = $this->createMock(ContainerInterface::class);
        $this->factory = new ConsoleEventDispatcherFactory();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testCreatesEventDispatcherWithSubscriber(): void
    {
        $listener = new class implements EventSubscriberInterface {
            public static function getSubscribedEvents(): array
            {
                return ['console.error' => 'onError'];
            }
        };

        $this->containerMock->expects($this->once())
            ->method('get')
            ->with(SentryCommandListener::class)
            ->willReturn($listener)
        ;

        $dispatcher = $this->factory->__invoke($this->containerMock);

        $this->assertInstanceOf(EventDispatcher::class, $dispatcher);
        $this->assertTrue($dispatcher->hasListeners('console.error'));
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function testThrowsWhenListenerNotFound(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);

        $this->containerMock->method('get')->willThrowException(
            $this->createMock(NotFoundExceptionInterface::class)
        );

        $this->factory->__invoke($this->containerMock);
    }
}
