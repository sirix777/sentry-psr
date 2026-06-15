<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Middleware;

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
use Sirix\SentryPsr\Middleware\SentryErrorMiddleware;
use Sirix\SentryPsr\Middleware\SentryErrorMiddlewareFactory;

/**
 * @internal
 */
#[CoversClass(SentryErrorMiddlewareFactory::class)]
final class SentryErrorMiddlewareFactoryTest extends TestCase
{
    private ContainerInterface $containerMock;
    private HubInterface $hubMock;
    private SentryErrorMiddlewareFactory $factory;

    public function setUp(): void
    {
        parent::setUp();

        $this->containerMock = $this->createMock(ContainerInterface::class);
        $this->hubMock       = $this->createMock(HubInterface::class);
        $this->factory       = new SentryErrorMiddlewareFactory();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testFactoryCreatesMiddlewareWithoutLogger(): void
    {
        $this->containerMock->method('get')
            ->with(HubInterface::class)
            ->willReturn($this->hubMock)
        ;
        $this->containerMock->method('has')
            ->willReturn(false)
        ;

        $middleware = $this->factory->__invoke($this->containerMock);

        $this->assertInstanceOf(SentryErrorMiddleware::class, $middleware);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws NotFoundExceptionInterface
     */
    public function testFactoryCreatesMiddlewareWithLoggerInterface(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $this->containerMock->method('get')
            ->willReturnMap([
                [HubInterface::class, $this->hubMock],
                [LoggerInterface::class, $logger],
            ])
        ;
        $this->containerMock->method('has')
            ->willReturnMap([
                [LoggerInterface::class, true],
                [Logger::class, false],
                ['logger', false],
            ])
        ;

        $middleware = $this->factory->__invoke($this->containerMock);

        $this->assertInstanceOf(SentryErrorMiddleware::class, $middleware);
        $this->assertSame($logger, (new ReflectionProperty($middleware, 'logger'))->getValue($middleware));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws NotFoundExceptionInterface
     */
    public function testFactoryCreatesMiddlewareWithMonologLogger(): void
    {
        $monolog = $this->createMock(Logger::class);

        $this->containerMock->method('get')
            ->willReturnMap([
                [HubInterface::class, $this->hubMock],
                [Logger::class, $monolog],
            ])
        ;
        $this->containerMock->method('has')
            ->willReturnMap([
                [LoggerInterface::class, false],
                [Logger::class, true],
                ['logger', false],
            ])
        ;

        $middleware = $this->factory->__invoke($this->containerMock);

        $this->assertInstanceOf(SentryErrorMiddleware::class, $middleware);
        $this->assertSame($monolog, (new ReflectionProperty($middleware, 'logger'))->getValue($middleware));
    }
}
