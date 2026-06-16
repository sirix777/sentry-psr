<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sentry\ClientInterface;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;

/**
 * @internal
 */
#[CoversClass(SentryLifecycle::class)]
final class SentryLifecycleTest extends TestCase
{
    public function testWithIsolatedScopeDelegatesToHub(): void
    {
        $scope = new Scope();
        $hub   = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('withScope')
            ->willReturnCallback(static function(callable $callback) use ($scope): string {
                return $callback($scope);
            })
        ;

        $result = (new SentryLifecycle($hub))->withIsolatedScope(
            static fn (Scope $currentScope): string => $currentScope === $scope ? 'ok' : 'fail',
        );

        $this->assertSame('ok', $result);
    }

    public function testFlushDelegatesToClientWithConfiguredTimeout(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('flush')
            ->with(7)
            ->willReturn(new Result(ResultStatus::success()))
        ;

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())->method('getClient')->willReturn($client);

        (new SentryLifecycle($hub, flushTimeout: 7))->flush();
    }

    public function testFlushLogsAndSuppressesClientErrors(): void
    {
        $exception = new RuntimeException('Transport failed');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('flush')->willThrowException($exception);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())->method('getClient')->willReturn($client);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Failed to flush Sentry client', [
                'exception' => $exception,
            ])
        ;

        (new SentryLifecycle($hub, logger: $logger))->flush();
    }
}
