<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Reporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sentry\State\HubInterface;
use Sirix\SentryPsr\ExceptionFilter\ExceptionFilterInterface;
use Sirix\SentryPsr\Reporter\SentryReporter;
use Sirix\SentryPsr\Reporter\SentryReporterFactory;
use Sirix\SentryPsr\Test\Container\InMemoryContainer;

/**
 * @internal
 */
#[CoversClass(SentryReporterFactory::class)]
final class SentryReporterFactoryTest extends TestCase
{
    public function testCreatesReporterWithHub(): void
    {
        $hub = $this->createMock(HubInterface::class);

        $reporter = (new SentryReporterFactory())->__invoke(new InMemoryContainer([
            HubInterface::class => $hub,
        ]));

        $this->assertInstanceOf(SentryReporter::class, $reporter);
        $this->assertSame($hub, (new ReflectionProperty($reporter, 'hub'))->getValue($reporter));
    }

    public function testCreatesReporterWithCustomExceptionFilter(): void
    {
        $hub             = $this->createMock(HubInterface::class);
        $exceptionFilter = $this->createMock(ExceptionFilterInterface::class);

        $reporter = (new SentryReporterFactory())->__invoke(new InMemoryContainer([
            HubInterface::class             => $hub,
            ExceptionFilterInterface::class => $exceptionFilter,
        ]));

        $this->assertInstanceOf(SentryReporter::class, $reporter);
        $this->assertSame($exceptionFilter, (new ReflectionProperty($reporter, 'exceptionFilter'))->getValue($reporter));
    }
}
