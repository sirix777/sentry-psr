<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Lifecycle;

use Psr\Log\LoggerInterface;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Throwable;

final readonly class SentryLifecycle
{
    public function __construct(private HubInterface $hub, private ?int $flushTimeout = 2, private ?LoggerInterface $logger = null) {}

    /**
     * @template T
     *
     * @param callable(Scope): T $callback
     *
     * @return T
     */
    public function withIsolatedScope(callable $callback): mixed
    {
        return $this->hub->withScope($callback);
    }

    public function pushScope(): Scope
    {
        return $this->hub->pushScope();
    }

    public function popScope(): bool
    {
        return $this->hub->popScope();
    }

    /**
     * @param callable(Scope): void $callback
     */
    public function configureScope(callable $callback): void
    {
        $this->hub->configureScope($callback);
    }

    public function flush(): void
    {
        try {
            $this->hub->getClient()?->flush($this->flushTimeout);
        } catch (Throwable $exception) {
            $this->logger?->warning('Failed to flush Sentry client', [
                'exception' => $exception,
            ]);
        }
    }
}
