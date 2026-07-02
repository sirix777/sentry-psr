<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\ExceptionFilter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\State\HubInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\SentryPsr\ExceptionFilter\ConfiguredExceptionFilter;
use Sirix\SentryPsr\ExceptionFilter\ConfiguredExceptionFilterFactory;
use Sirix\SentryPsr\ExceptionFilter\ExceptionFilterContext;
use Sirix\SentryPsr\ExceptionFilter\ExceptionFilterInterface;
use Sirix\SentryPsr\Test\Config\SentryPsrConfigFixture;
use Sirix\SentryPsr\Test\Container\InMemoryContainer;

/**
 * @internal
 */
#[CoversClass(ConfiguredExceptionFilterFactory::class)]
final class ConfiguredExceptionFilterFactoryTest extends TestCase
{
    public function testFactoryCreatesConfiguredFilterFromContainer(): void
    {
        $filter = (new ConfiguredExceptionFilterFactory())->__invoke(new InMemoryContainer([
            HubInterface::class => $this->createMock(HubInterface::class),
            'config'            => SentryPsrConfigFixture::config([
                'exception_filter' => [
                    'ignore_http_statuses' => [
                        401,
                    ],
                ],
            ]),
        ]));

        $this->assertInstanceOf(ExceptionFilterInterface::class, $filter);
        $this->assertFalse($filter->shouldCapture(new RuntimeException('Unauthorized', 401), ExceptionFilterContext::reporter()));
    }

    public function testCreatesConfiguredFilterFromConfigReader(): void
    {
        $configReader = ConfigReader::fromContainer(ContainerResolver::forFactory(new InMemoryContainer([
            'config' => SentryPsrConfigFixture::config([
                'exception_filter' => [
                    'ignore_codes' => [
                        9001,
                    ],
                ],
            ]),
        ]), self::class));

        $filter = ConfiguredExceptionFilterFactory::fromConfigReader($configReader);

        $this->assertInstanceOf(ConfiguredExceptionFilter::class, $filter);
        $this->assertFalse($filter->shouldCapture(new RuntimeException('Domain error', 9001), ExceptionFilterContext::reporter()));
    }
}
