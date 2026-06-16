<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Hub;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sirix\ContainerResolver\Exception\MissingConfigValueException;
use Sirix\SentryPsr\Hub\SentryHubFactory;
use Sirix\SentryPsr\Test\Config\SentryPsrConfigFixture;
use Sirix\SentryPsr\Test\Container\InMemoryContainer;

/**
 * @internal
 */
#[CoversClass(SentryHubFactory::class)]
final class SentryHubFactoryTest extends TestCase
{
    private SentryHubFactory $factory;

    public function setUp(): void
    {
        parent::setUp();

        $this->factory = new SentryHubFactory();

        SentrySdk::setCurrentHub(new Hub());
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function testCreatesHubWithEmptyConfig(): void
    {
        $hub = $this->factory->__invoke(new InMemoryContainer([
            'config' => SentryPsrConfigFixture::config(),
        ]));

        $this->assertInstanceOf(HubInterface::class, $hub);
        $this->assertSame($hub, SentrySdk::getCurrentHub());
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function testCreatesHubWithCustomConfig(): void
    {
        $dsn = 'https://examplePublicKey@o0.ingest.sentry.io/0';

        $hub = $this->factory->__invoke(new InMemoryContainer([
            'config' => [
                'sentry' => [
                    'dsn'         => $dsn,
                    'environment' => 'test',
                ],
                ...SentryPsrConfigFixture::config(),
            ],
        ]));

        $this->assertInstanceOf(HubInterface::class, $hub);
        $this->assertSame($hub, SentrySdk::getCurrentHub());
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function testCreatesHubWithEmptyDsnConfig(): void
    {
        $hub = $this->factory->__invoke(new InMemoryContainer([
            'config' => [
                'sentry' => [
                    'dsn'         => null,
                    'environment' => 'test',
                ],
                ...SentryPsrConfigFixture::config(),
            ],
        ]));

        $this->assertInstanceOf(HubInterface::class, $hub);
        $this->assertSame($hub, SentrySdk::getCurrentHub());
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function testThrowsWhenSentryPsrConfigIsMissing(): void
    {
        $this->expectException(MissingConfigValueException::class);

        $this->factory->__invoke(new InMemoryContainer());
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function testFactoryCanCreateHubWithoutSettingGlobalCurrentHub(): void
    {
        $previousHub = SentrySdk::getCurrentHub();

        $hub = $this->factory->__invoke(new InMemoryContainer([
            'config' => SentryPsrConfigFixture::config([
                'set_current_hub' => false,
            ]),
        ]));

        $this->assertInstanceOf(HubInterface::class, $hub);
        $this->assertNotSame($hub, $previousHub);
        $this->assertSame($previousHub, SentrySdk::getCurrentHub());
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function testRepeatedFactoryCallsCreateIndependentHubs(): void
    {
        $firstHub = $this->factory->__invoke(new InMemoryContainer([
            'config' => SentryPsrConfigFixture::config(),
        ]));
        $secondHub = $this->factory->__invoke(new InMemoryContainer([
            'config' => SentryPsrConfigFixture::config(),
        ]));

        $this->assertNotSame($firstHub, $secondHub);
        $this->assertSame($secondHub, SentrySdk::getCurrentHub());
    }
}
