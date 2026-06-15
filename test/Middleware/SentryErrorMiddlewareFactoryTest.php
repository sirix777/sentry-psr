<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Middleware;

use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use Sentry\State\HubInterface;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Middleware\SentryErrorMiddleware;
use Sirix\SentryPsr\Middleware\SentryErrorMiddlewareFactory;
use Sirix\SentryPsr\Test\Config\SentryPsrConfigFixture;
use Sirix\SentryPsr\Test\Container\InMemoryContainer;

/**
 * @internal
 */
#[CoversClass(SentryErrorMiddlewareFactory::class)]
final class SentryErrorMiddlewareFactoryTest extends TestCase
{
    private HubInterface $hubMock;
    private SentryLifecycle $lifecycle;
    private SentryErrorMiddlewareFactory $factory;

    public function setUp(): void
    {
        parent::setUp();

        $this->hubMock   = $this->createMock(HubInterface::class);
        $this->lifecycle = new SentryLifecycle($this->hubMock);
        $this->factory   = new SentryErrorMiddlewareFactory();
    }

    public function testFactoryCreatesMiddlewareWithoutLogger(): void
    {
        $middleware = $this->factory->__invoke(new InMemoryContainer([
            HubInterface::class    => $this->hubMock,
            SentryLifecycle::class => $this->lifecycle,
            'config'               => SentryPsrConfigFixture::config(),
        ]));

        $this->assertInstanceOf(SentryErrorMiddleware::class, $middleware);
    }

    public function testFactoryCreatesMiddlewareWithLoggerInterface(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $middleware = $this->factory->__invoke(new InMemoryContainer([
            HubInterface::class      => $this->hubMock,
            SentryLifecycle::class   => $this->lifecycle,
            LoggerInterface::class   => $logger,
            'config'                 => SentryPsrConfigFixture::config(),
        ]));

        $this->assertInstanceOf(SentryErrorMiddleware::class, $middleware);
        $this->assertSame($logger, (new ReflectionProperty($middleware, 'logger'))->getValue($middleware));
    }

    public function testFactoryCreatesMiddlewareWithMonologLogger(): void
    {
        $monolog = $this->createMock(Logger::class);

        $middleware = $this->factory->__invoke(new InMemoryContainer([
            HubInterface::class    => $this->hubMock,
            SentryLifecycle::class => $this->lifecycle,
            'Monolog\Logger'       => $monolog,
            'config'               => SentryPsrConfigFixture::config(),
        ]));

        $this->assertInstanceOf(SentryErrorMiddleware::class, $middleware);
        $this->assertSame($monolog, (new ReflectionProperty($middleware, 'logger'))->getValue($middleware));
    }

    public function testFactoryAppliesHttpConfigurationFlags(): void
    {
        $middleware = $this->factory->__invoke(new InMemoryContainer([
            HubInterface::class    => $this->hubMock,
            SentryLifecycle::class => $this->lifecycle,
            'config'               => SentryPsrConfigFixture::config([
                'isolate_http_scope'           => false,
                'flush_on_http_error'          => true,
                'capture_http_request_context' => true,
                'http_context'                 => [
                    'enabled'         => false,
                    'capture_headers' => true,
                ],
            ]),
        ]));

        $this->assertFalse((new ReflectionProperty($middleware, 'isolateScope'))->getValue($middleware));
        $this->assertTrue((new ReflectionProperty($middleware, 'flushOnHttpError'))->getValue($middleware));
        $this->assertFalse((new ReflectionProperty($middleware, 'captureRequestContext'))->getValue($middleware));
        $httpContext = (new ReflectionProperty($middleware, 'httpContext'))->getValue($middleware);
        $this->assertFalse($httpContext['enabled']);
        $this->assertTrue($httpContext['capture_headers']);
    }
}
