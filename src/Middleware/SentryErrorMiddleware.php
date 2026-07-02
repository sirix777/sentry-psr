<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sirix\SentryPsr\ExceptionFilter\ExceptionFilterContext;
use Sirix\SentryPsr\ExceptionFilter\ExceptionFilterInterface;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Throwable;

use function array_fill_keys;
use function array_key_exists;
use function array_keys;
use function array_map;
use function in_array;
use function is_scalar;
use function sprintf;
use function strtolower;

class SentryErrorMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, mixed> $httpContext
     */
    public function __construct(
        private readonly HubInterface $hub,
        private readonly bool $isolateScope = true,
        private readonly bool $flushOnHttpError = false,
        private readonly bool $captureRequestContext = true,
        private readonly array $httpContext = [],
        private readonly ?LoggerInterface $logger = null,
        private readonly ?SentryLifecycle $sentryLifecycle = null,
        private readonly ?ExceptionFilterInterface $exceptionFilter = null,
    ) {}

    /**
     * @throws Throwable
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $lifecycle = $this->sentryLifecycle ?? new SentryLifecycle($this->hub, logger: $this->logger);

        if (! $this->isolateScope) {
            return $this->processWithinScope($request, $handler, null, $lifecycle);
        }

        return $lifecycle->withIsolatedScope(
            fn (Scope $scope): ResponseInterface => $this->processWithinScope($request, $handler, $scope, $lifecycle)
        );
    }

    /**
     * @throws Throwable
     */
    private function processWithinScope(
        ServerRequestInterface $serverRequest,
        RequestHandlerInterface $requestHandler,
        ?Scope $scope,
        SentryLifecycle $sentryLifecycle,
    ): ResponseInterface {
        if ($this->captureRequestContext) {
            if ($scope instanceof Scope) {
                $this->configureRequestScope($scope, $serverRequest);
            } else {
                $sentryLifecycle->configureScope(fn (Scope $currentScope): null => $this->configureRequestScope($currentScope, $serverRequest));
            }
        }

        $capturedException = false;

        try {
            return $requestHandler->handle($serverRequest);
        } catch (Throwable $exception) {
            if (! $this->shouldCaptureException($exception, ExceptionFilterContext::http($serverRequest))) {
                throw $exception;
            }

            $capturedException = true;
            $this->hub->captureException($exception);
            $this->logger?->error($exception->getMessage(), [
                'exception'    => $exception,
                'request_path' => $serverRequest->getUri()->getPath(),
            ]);

            throw $exception;
        } finally {
            if ($capturedException && $this->flushOnHttpError) {
                $sentryLifecycle->flush();
            }
        }
    }

    private function shouldCaptureException(Throwable $throwable, ExceptionFilterContext $exceptionFilterContext): bool
    {
        return ! $this->exceptionFilter instanceof ExceptionFilterInterface || $this->exceptionFilter->shouldCapture($throwable, $exceptionFilterContext);
    }

    private function configureRequestScope(Scope $scope, ServerRequestInterface $serverRequest): null
    {
        $uri       = $serverRequest->getUri();
        $requestId = $this->resolveRequestId($serverRequest);

        $scope->setTag('http.method', $serverRequest->getMethod());

        if (null !== $requestId) {
            $scope->setTag('request_id', $requestId);
        }

        $context = [
            'method' => $serverRequest->getMethod(),
            'path'   => $uri->getPath(),
            'host'   => $uri->getHost(),
            'scheme' => $uri->getScheme(),
        ];

        if ($this->captureQueryString()) {
            $context['query_string']   = $uri->getQuery();
            $context['request_target'] = $serverRequest->getRequestTarget();
        }

        if (null !== $requestId) {
            $context['request_id'] = $requestId;
        }

        $attributes = $this->filteredAttributes($serverRequest);
        if ([] !== $attributes) {
            $context['attributes'] = $attributes;
        }

        $headers = $this->filteredHeaders($serverRequest);
        if ([] !== $headers) {
            $context['headers'] = $headers;
        }

        $scope->setContext('request', $context);

        return null;
    }

    private function captureQueryString(): bool
    {
        return true === ($this->httpContext['capture_query_string'] ?? false);
    }

    /**
     * @return array<string, null|scalar>
     */
    private function filteredAttributes(ServerRequestInterface $serverRequest): array
    {
        /** @var list<string> $allowedAttributes */
        $allowedAttributes = $this->httpContext['allowed_attributes'] ?? [
            'route',
            'route_name',
            'request_id',
            'correlation_id',
        ];

        $attributes = [];
        foreach ($allowedAttributes as $allowedAttribute) {
            $value = $serverRequest->getAttribute($allowedAttribute);

            if (is_scalar($value) || null === $value) {
                $attributes[$allowedAttribute] = $value;
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    private function filteredHeaders(ServerRequestInterface $serverRequest): array
    {
        $captureHeaders = $this->httpContext['capture_headers'] ?? false;

        if (true !== $captureHeaders) {
            return [];
        }

        /** @var list<string> $allowedHeaders */
        $allowedHeaders = $this->httpContext['allowed_headers'] ?? [
            'User-Agent',
            'X-Request-Id',
        ];

        $allowed = array_fill_keys(
            array_map(strtolower(...), $allowedHeaders),
            true,
        );

        $blocked = array_fill_keys([
            'authorization',
            'cookie',
            'set-cookie',
            'x-api-key',
            'x-auth-token',
            'x-csrf-token',
        ], true);

        $headers = [];
        foreach (array_keys($serverRequest->getHeaders()) as $name) {
            $normalized = strtolower((string) $name);
            if (! array_key_exists($normalized, $allowed)) {
                continue;
            }

            if (array_key_exists($normalized, $blocked)) {
                continue;
            }

            $headers[$name] = $serverRequest->getHeaderLine($name);
        }

        return $headers;
    }

    private function resolveRequestId(ServerRequestInterface $serverRequest): ?string
    {
        /** @var list<string> $headers */
        $headers = $this->httpContext['request_id_headers'] ?? [
            'X-Request-Id',
            'X-Correlation-Id',
        ];

        foreach ($headers as $header) {
            $value = $serverRequest->getHeaderLine($header);

            if ('' !== $value) {
                return $value;
            }
        }

        /** @var list<string> $attributes */
        $attributes = $this->httpContext['request_id_attributes'] ?? [
            'request_id',
            'requestId',
            'correlation_id',
            'correlationId',
        ];

        foreach ($attributes as $attribute) {
            $value = $serverRequest->getAttribute($attribute);

            if (is_scalar($value) && ! in_array((string) $value, ['', '0'], true)) {
                return sprintf('%s', $value);
            }
        }

        return null;
    }
}
