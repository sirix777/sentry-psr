<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Listener;

use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use ReflectionException;
use ReflectionProperty;
use Sentry\State\HubInterface;
use Sirix\SentryPsr\Listener\SentryCommandListener;
use Sirix\SentryPsr\Listener\SentryCommandListenerFactory;

/**
 * @internal
 */
#[CoversClass(SentryCommandListenerFactory::class)]
final class SentryCommandListenerFactoryTest extends TestCase
{
    private HubInterface $hubMock;
    private ContainerInterface $containerMock;
    private SentryCommandListenerFactory $factory;

    public function setUp(): void
    {
        parent::setUp();

        $this->hubMock = $this->createMock(HubInterface::class);
        $this->containerMock = $this->createMock(ContainerInterface::class);
        $this->factory = new SentryCommandListenerFactory();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws NotFoundExceptionInterface
     */
    public function testCreatesListenerWithoutLogger(): void
    {
        $this->containerMock->method('get')->willReturnMap([
            [HubInterface::class, $this->hubMock],
        ]);
        $this->containerMock->method('has')->willReturn(false);

        $listener = $this->factory->__invoke($this->containerMock);

        $this->assertInstanceOf(SentryCommandListener::class, $listener);
        $this->assertNull((new ReflectionProperty($listener, 'logger'))->getValue($listener));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws NotFoundExceptionInterface
     */
    public function testCreatesListenerWithPsrLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $this->containerMock->method('get')->willReturnMap([
            [HubInterface::class, $this->hubMock],
            [LoggerInterface::class, $logger],
        ]);
        $this->containerMock->method('has')->willReturnMap([
            [LoggerInterface::class, true],
            [Logger::class, false],
            ['logger', false],
        ]);

        $listener = $this->factory->__invoke($this->containerMock);

        $this->assertInstanceOf(SentryCommandListener::class, $listener);
        $this->assertSame($logger, (new ReflectionProperty($listener, 'logger'))->getValue($listener));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws NotFoundExceptionInterface
     */
    public function testCreatesListenerWithMonologLogger(): void
    {
        $logger = $this->createMock(Logger::class);

        $this->containerMock->method('get')->willReturnMap([
            [HubInterface::class, $this->hubMock],
            [Logger::class, $logger],
        ]);
        $this->containerMock->method('has')->willReturnMap([
            [LoggerInterface::class, false],
            [Logger::class, true],
            ['logger', false],
        ]);

        $listener = $this->factory->__invoke($this->containerMock);

        $this->assertInstanceOf(SentryCommandListener::class, $listener);
        $this->assertSame($logger, (new ReflectionProperty($listener, 'logger'))->getValue($listener));
    }

    /**
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testCreatesListenerWithLoggerAlias(): void
    {
        $logger = $this->createMock(Logger::class);

        $this->containerMock->method('get')->willReturnMap([
            [HubInterface::class, $this->hubMock],
            ['logger', $logger],
        ]);
        $this->containerMock->method('has')->willReturnMap([
            [LoggerInterface::class, false],
            [Logger::class, false],
            ['logger', true],
        ]);

        $listener = $this->factory->__invoke($this->containerMock);

        $this->assertInstanceOf(SentryCommandListener::class, $listener);
        $this->assertSame($logger, (new ReflectionProperty($listener, 'logger'))->getValue($listener));
    }
}
