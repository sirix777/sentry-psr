<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Hub;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sirix\SentryPsr\Hub\SentryHubFactory;

/**
 * @internal
 */
#[CoversClass(SentryHubFactory::class)]
final class SentryHubFactoryTest extends TestCase
{
    private ContainerInterface $containerMock;
    private SentryHubFactory $factory;

    public function setUp(): void
    {
        parent::setUp();

        $this->containerMock = $this->createMock(ContainerInterface::class);
        $this->factory = new SentryHubFactory();

        SentrySdk::setCurrentHub(new Hub());
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testCreatesHubWithEmptyConfig(): void
    {
        $this->containerMock->method('get')->with('config')->willReturn([]);

        $hub = $this->factory->__invoke($this->containerMock);

        $this->assertInstanceOf(HubInterface::class, $hub);
        $this->assertSame($hub, SentrySdk::getCurrentHub());
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testCreatesHubWithCustomConfig(): void
    {
        $dsn = 'https://examplePublicKey@o0.ingest.sentry.io/0';

        $this->containerMock->method('get')->with('config')->willReturn([
            'sentry' => [
                'dsn' => $dsn,
                'environment' => 'test',
            ],
        ]);

        $hub = $this->factory->__invoke($this->containerMock);

        $this->assertInstanceOf(HubInterface::class, $hub);
        $this->assertSame($hub, SentrySdk::getCurrentHub());
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testCreatesHubWithEmptyDsnConfig(): void
    {
        $this->containerMock->method('get')->with('config')->willReturn([
            'sentry' => [
                'dsn' => null,
                'environment' => 'test',
            ],
        ]);

        $hub = $this->factory->__invoke($this->containerMock);

        $this->assertInstanceOf(HubInterface::class, $hub);
        $this->assertSame($hub, SentrySdk::getCurrentHub());
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function testThrowsIfConfigNotFound(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);

        $this->containerMock->method('get')->willThrowException(
            $this->createMock(NotFoundExceptionInterface::class)
        );

        $this->factory->__invoke($this->containerMock);
    }
}
