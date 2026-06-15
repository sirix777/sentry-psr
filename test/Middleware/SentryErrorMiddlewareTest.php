<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sentry\ClientInterface;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Middleware\SentryErrorMiddleware;
use Throwable;

/**
 * @internal
 */
#[CoversClass(SentryErrorMiddleware::class)]
class SentryErrorMiddlewareTest extends TestCase
{
    private HubInterface&MockObject $hubMock;
    private LoggerInterface&MockObject $loggerMock;
    private MockObject&ServerRequestInterface $requestMock;
    private MockObject&RequestHandlerInterface $handlerMock;
    private ResponseInterface $responseMock;
    private MockObject&Scope $scopeMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->hubMock      = $this->createMock(HubInterface::class);
        $this->loggerMock   = $this->createMock(LoggerInterface::class);
        $this->requestMock  = $this->createMock(ServerRequestInterface::class);
        $this->handlerMock  = $this->createMock(RequestHandlerInterface::class);
        $this->responseMock = $this->createMock(ResponseInterface::class);
        $this->scopeMock    = $this->createMock(Scope::class);

        $this->hubMock->method('withScope')->willReturnCallback(function(callable $callback): mixed {
            return $callback($this->scopeMock);
        });

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/test-path');
        $uri->method('getQuery')->willReturn('foo=bar');
        $uri->method('getHost')->willReturn('example.com');
        $uri->method('getScheme')->willReturn('https');

        $this->requestMock->method('getUri')->willReturn($uri);
        $this->requestMock->method('getMethod')->willReturn('GET');
        $this->requestMock->method('getRequestTarget')->willReturn('/test-path?foo=bar');
        $this->requestMock->method('getHeaders')->willReturn([
            'User-Agent'    => ['PHPUnit'],
            'Authorization' => ['Bearer secret'],
        ]);
        $this->requestMock->method('getHeaderLine')->willReturnMap([
            ['X-Request-Id', 'req-123'],
            ['X-Correlation-Id', ''],
            ['User-Agent', 'PHPUnit'],
            ['Authorization', 'Bearer secret'],
        ]);
        $this->requestMock->method('getAttribute')->willReturnCallback(static fn (string $name): mixed => match ($name) {
            'route', 'route_name' => 'home',
            default               => null,
        });
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
                        && '/test-path' === $context['request_path'];
                })
            )
        ;

        $this->handlerMock->method('handle')
            ->willThrowException($exception)
        ;

        $middleware = new SentryErrorMiddleware(
            $this->hubMock,
            logger: $this->loggerMock,
            sentryLifecycle: new SentryLifecycle($this->hubMock, logger: $this->loggerMock),
        );

        $this->expectExceptionObject($exception);

        $middleware->process($this->requestMock, $this->handlerMock);
    }

    /**
     * @throws Throwable
     */
    public function testProcessPassesThroughResponseIfNoException(): void
    {
        $this->handlerMock->method('handle')->willReturn($this->responseMock);

        $middleware = new SentryErrorMiddleware(
            $this->hubMock,
            logger: $this->loggerMock,
            sentryLifecycle: new SentryLifecycle($this->hubMock, logger: $this->loggerMock),
        );

        $this->assertSame($this->responseMock, $middleware->process($this->requestMock, $this->handlerMock));
    }

    /**
     * @throws Throwable
     */
    public function testProcessConfiguresRequestContextWithoutSensitiveHeaders(): void
    {
        $this->handlerMock->method('handle')->willReturn($this->responseMock);

        $this->scopeMock->expects($this->exactly(2))->method('setTag');
        $this->scopeMock->expects($this->once())
            ->method('setContext')
            ->with('request', $this->callback(static function(array $context): bool {
                return 'GET' === $context['method']
                    && '/test-path' === $context['path']
                    && 'req-123' === $context['request_id']
                    && [
                        'User-Agent' => 'PHPUnit',
                    ] === $context['headers']
                    && ! isset($context['headers']['Authorization'])
                    && ! isset($context['query_string'], $context['request_target']);
            }))
        ;

        $middleware = new SentryErrorMiddleware(
            $this->hubMock,
            httpContext: [
                'capture_headers' => true,
                'allowed_headers' => [
                    'User-Agent',
                    'Authorization',
                ],
            ],
            logger: $this->loggerMock,
            sentryLifecycle: new SentryLifecycle($this->hubMock, logger: $this->loggerMock),
        );

        $this->assertSame($this->responseMock, $middleware->process($this->requestMock, $this->handlerMock));
    }

    /**
     * @throws Throwable
     */
    public function testCanOptInToQueryStringCapture(): void
    {
        $this->handlerMock->method('handle')->willReturn($this->responseMock);

        $this->scopeMock->expects($this->exactly(2))->method('setTag');
        $this->scopeMock->expects($this->once())
            ->method('setContext')
            ->with('request', $this->callback(static function(array $context): bool {
                return 'foo=bar' === $context['query_string']
                    && '/test-path?foo=bar' === $context['request_target'];
            }))
        ;

        $middleware = new SentryErrorMiddleware(
            $this->hubMock,
            httpContext: [
                'capture_query_string' => true,
            ],
            logger: $this->loggerMock,
            sentryLifecycle: new SentryLifecycle($this->hubMock, logger: $this->loggerMock),
        );

        $this->assertSame($this->responseMock, $middleware->process($this->requestMock, $this->handlerMock));
    }

    /**
     * @throws Throwable
     */
    public function testFlushesOnlyWhenHttpErrorIsCaptured(): void
    {
        $exception = new RuntimeException('Test exception');
        $client    = $this->createMock(ClientInterface::class);

        $client->expects($this->once())
            ->method('flush')
            ->with(2)
            ->willReturn(new Result(ResultStatus::success()))
        ;

        $this->hubMock->expects($this->once())->method('captureException')->with($exception);
        $this->hubMock->expects($this->once())->method('getClient')->willReturn($client);
        $this->handlerMock->method('handle')->willThrowException($exception);

        $middleware = new SentryErrorMiddleware(
            $this->hubMock,
            flushOnHttpError: true,
            sentryLifecycle: new SentryLifecycle($this->hubMock),
        );

        $this->expectExceptionObject($exception);

        $middleware->process($this->requestMock, $this->handlerMock);
    }

    /**
     * @throws Throwable
     */
    public function testDoesNotFlushSuccessfulHttpRequestEvenWhenFlushOnHttpErrorIsEnabled(): void
    {
        $this->hubMock->expects($this->never())->method('getClient');
        $this->handlerMock->method('handle')->willReturn($this->responseMock);

        $middleware = new SentryErrorMiddleware(
            $this->hubMock,
            flushOnHttpError: true,
            sentryLifecycle: new SentryLifecycle($this->hubMock),
        );

        $this->assertSame($this->responseMock, $middleware->process($this->requestMock, $this->handlerMock));
    }

    /**
     * @throws Throwable
     */
    public function testCanDisableRequestContextCapture(): void
    {
        $this->scopeMock->expects($this->never())->method('setTag');
        $this->scopeMock->expects($this->never())->method('setContext');
        $this->handlerMock->method('handle')->willReturn($this->responseMock);

        $middleware = new SentryErrorMiddleware(
            $this->hubMock,
            captureRequestContext: false,
            sentryLifecycle: new SentryLifecycle($this->hubMock),
        );

        $this->assertSame($this->responseMock, $middleware->process($this->requestMock, $this->handlerMock));
    }

    /**
     * @throws Throwable
     */
    public function testConfiguresCurrentScopeWhenHttpScopeIsolationIsDisabled(): void
    {
        $this->hubMock->expects($this->never())->method('withScope');
        $this->hubMock->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function(callable $callback): bool {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->exactly(2))->method('setTag');
                $scope->expects($this->once())->method('setContext')->with('request', $this->isType('array'));

                $callback($scope);

                return true;
            }))
        ;
        $this->handlerMock->method('handle')->willReturn($this->responseMock);

        $middleware = new SentryErrorMiddleware(
            $this->hubMock,
            isolateScope: false,
            sentryLifecycle: new SentryLifecycle($this->hubMock),
        );

        $this->assertSame($this->responseMock, $middleware->process($this->requestMock, $this->handlerMock));
    }

    /**
     * @throws Throwable
     */
    public function testRequestIdFallsBackToAllowedScalarAttribute(): void
    {
        $this->handlerMock->method('handle')->willReturn($this->responseMock);

        $this->scopeMock->expects($this->exactly(2))->method('setTag');
        $this->scopeMock->expects($this->once())
            ->method('setContext')
            ->with('request', $this->callback(static fn (array $context): bool => 'home' === $context['request_id']))
        ;

        $middleware = new SentryErrorMiddleware(
            $this->hubMock,
            httpContext: [
                'request_id_headers'    => [],
                'request_id_attributes' => [
                    'route',
                ],
            ],
            sentryLifecycle: new SentryLifecycle($this->hubMock),
        );

        $this->assertSame($this->responseMock, $middleware->process($this->requestMock, $this->handlerMock));
    }
}
