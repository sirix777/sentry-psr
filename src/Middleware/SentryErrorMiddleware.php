<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sentry\State\HubInterface;
use Throwable;

class SentryErrorMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly HubInterface $hub, private readonly ?LoggerInterface $logger = null) {}

    /**
     * @throws Throwable
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            $this->hub->captureException($exception);

            $this->logger?->error(
                $exception->getMessage(),
                [
                    'exception'    => $exception,
                    'request_path' => $request->getUri()->getPath(),
                ]
            );

            throw $exception;
        }
    }
}
