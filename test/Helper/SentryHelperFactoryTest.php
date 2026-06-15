<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionProperty;
use Sentry\State\HubInterface;
use Sirix\SentryPsr\Helper\SentryHelper;
use Sirix\SentryPsr\Helper\SentryHelperFactory;

/**
 * @internal
 */
#[CoversClass(SentryHelperFactory::class)]
class SentryHelperFactoryTest extends TestCase
{
    private SentryHelperFactory $factory;
    private ContainerInterface $containerMock;
    private HubInterface $hubMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->factory       = new SentryHelperFactory();
        $this->hubMock       = $this->createMock(HubInterface::class);
        $this->containerMock = $this->createMock(ContainerInterface::class);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testFactorySetsHub(): void
    {
        $this->containerMock->expects($this->once())
            ->method('has')
            ->with(HubInterface::class)
            ->willReturn(true)
        ;

        $this->containerMock->expects($this->once())
            ->method('get')
            ->with(HubInterface::class)
            ->willReturn($this->hubMock)
        ;

        $this->resetSentryHub();

        $result = $this->factory->__invoke($this->containerMock);

        $this->assertSame(SentryHelper::class, $result);

        $this->assertSame($this->hubMock, SentryHelper::getHub());
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testFactoryDoesNotSetHubIfNotExists(): void
    {
        $this->containerMock->expects($this->once())
            ->method('has')
            ->with(HubInterface::class)
            ->willReturn(false)
        ;

        $this->containerMock->expects($this->never())
            ->method('get')
        ;

        $this->resetSentryHub();

        $result = $this->factory->__invoke($this->containerMock);

        $this->assertSame(SentryHelper::class, $result);

        $this->assertNull((new ReflectionProperty(SentryHelper::class, 'hub'))->getValue());
    }

    private function resetSentryHub(): void
    {
        $reflection = new ReflectionProperty(SentryHelper::class, 'hub');
        $reflection->setValue(null, null);
    }
}
