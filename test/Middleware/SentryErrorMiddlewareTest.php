<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sentry\State\HubInterface;
use Sirix\SentryPsr\Middleware\SentryErrorMiddleware;
use Throwable;

/**
 * @internal
 */
#[CoversClass(SentryErrorMiddleware::class)]
class SentryErrorMiddlewareTest extends TestCase
{
    private HubInterface $hubMock;
    private LoggerInterface $loggerMock;
    private ServerRequestInterface $requestMock;
    private RequestHandlerInterface $handlerMock;
    private ResponseInterface $responseMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->hubMock = $this->createMock(HubInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->requestMock = $this->createMock(ServerRequestInterface::class);
        $this->handlerMock = $this->createMock(RequestHandlerInterface::class);
        $this->responseMock = $this->createMock(ResponseInterface::class);
    }

    /**
     * @throws Throwable
     */
    public function testProcessHandlesExceptionAndLogs(): void
    {
        $exception = new RuntimeException('Test exception');

        $this->hubMock->expects($this->once())
            ->method('captureException')
            ->with($exception)
        ;

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with(
                $exception->getMessage(),
                $this->callback(function($context) use ($exception) {
                    return $context['exception'] === $exception
                        && isset($context['request_path']);
                })
            )
        ;

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/test-path');

        $this->requestMock->method('getUri')->willReturn($uri);

        $this->handlerMock->method('handle')
            ->willThrowException($exception)
        ;

        $middleware = new SentryErrorMiddleware($this->hubMock, $this->loggerMock);

        $this->expectExceptionObject($exception);

        $middleware->process($this->requestMock, $this->handlerMock);
    }

    /**
     * @throws Throwable
     */
    public function testProcessPassesThroughResponseIfNoException(): void
    {
        $this->handlerMock->method('handle')->willReturn($this->responseMock);

        $middleware = new SentryErrorMiddleware($this->hubMock, $this->loggerMock);

        $this->assertSame($this->responseMock, $middleware->process($this->requestMock, $this->handlerMock));
    }
}
