<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sentry\ClientInterface;
use Sentry\State\HubInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Lifecycle\SentryLifecycleFactory;
use Sirix\SentryPsr\Test\Config\SentryPsrConfigFixture;
use Sirix\SentryPsr\Test\Container\InMemoryContainer;

/**
 * @internal
 */
#[CoversClass(SentryLifecycleFactory::class)]
final class SentryLifecycleFactoryTest extends TestCase
{
    public function testCreatesLifecycleWithConfiguredFlushTimeout(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('flush')
            ->with(9)
            ->willReturn(new Result(ResultStatus::success()))
        ;

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())->method('getClient')->willReturn($client);

        $lifecycle = (new SentryLifecycleFactory())->__invoke(new InMemoryContainer([
            HubInterface::class => $hub,
            'config'            => SentryPsrConfigFixture::config([
                'flush_timeout' => 9,
            ]),
        ]));

        $this->assertInstanceOf(SentryLifecycle::class, $lifecycle);

        $lifecycle->flush();
    }

    public function testCreatesLifecycleWithLogger(): void
    {
        $exception = new RuntimeException('Flush failed');
        $logger    = $this->createMock(LoggerInterface::class);
        $client    = $this->createMock(ClientInterface::class);
        $hub       = $this->createMock(HubInterface::class);

        $client->expects($this->once())->method('flush')->willThrowException($exception);
        $hub->expects($this->once())->method('getClient')->willReturn($client);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Failed to flush Sentry client', [
                'exception' => $exception,
            ])
        ;

        $lifecycle = (new SentryLifecycleFactory())->__invoke(new InMemoryContainer([
            HubInterface::class    => $hub,
            LoggerInterface::class => $logger,
            'config'               => SentryPsrConfigFixture::config(),
        ]));

        $lifecycle->flush();
    }
}
