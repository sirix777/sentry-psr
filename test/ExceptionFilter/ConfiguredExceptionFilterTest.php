<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\ExceptionFilter;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Sirix\SentryPsr\ExceptionFilter\ConfiguredExceptionFilter;
use Sirix\SentryPsr\ExceptionFilter\ExceptionFilterContext;

/**
 * @internal
 */
#[CoversClass(ConfiguredExceptionFilter::class)]
#[CoversClass(ExceptionFilterContext::class)]
final class ConfiguredExceptionFilterTest extends TestCase
{
    public function testAllowsExceptionWhenNoRulesMatch(): void
    {
        $filter = new ConfiguredExceptionFilter();

        $this->assertTrue($filter->shouldCapture(new RuntimeException('Failure'), ExceptionFilterContext::reporter()));
    }

    public function testDisabledFilterAllowsMatchingExceptions(): void
    {
        $filter = new ConfiguredExceptionFilter(
            enabled: false,
            ignoredClasses: [
                RuntimeException::class,
            ],
        );

        $this->assertTrue($filter->shouldCapture(new RuntimeException('Failure'), ExceptionFilterContext::reporter()));
    }

    public function testIgnoresByClassIncludingPreviousThrowable(): void
    {
        $filter = new ConfiguredExceptionFilter(ignoredClasses: [
            RuntimeException::class,
        ]);

        $exception = new LogicException('Wrapped', previous: new RuntimeException('Auth expired'));

        $this->assertFalse($filter->shouldCapture($exception, ExceptionFilterContext::reporter()));
    }

    public function testCanDisablePreviousThrowableInspection(): void
    {
        $filter = new ConfiguredExceptionFilter(
            ignoredClasses: [
                RuntimeException::class,
            ],
            inspectPrevious: false,
        );

        $exception = new LogicException('Wrapped', previous: new RuntimeException('Auth expired'));

        $this->assertTrue($filter->shouldCapture($exception, ExceptionFilterContext::reporter()));
    }

    public function testIgnoresByHttpStatusMethod(): void
    {
        $filter = new ConfiguredExceptionFilter(ignoredHttpStatuses: [
            401,
        ]);

        $this->assertFalse($filter->shouldCapture(new HttpStatusException(401), ExceptionFilterContext::reporter()));
    }

    public function testIgnoresByHttpStatusStoredInThrowableCode(): void
    {
        $filter = new ConfiguredExceptionFilter(ignoredHttpStatuses: [
            401,
        ]);

        $this->assertFalse($filter->shouldCapture(new RuntimeException('Unauthorized', 401), ExceptionFilterContext::reporter()));
    }

    public function testIgnoresByThrowableCode(): void
    {
        $filter = new ConfiguredExceptionFilter(ignoredCodes: [
            9001,
        ]);

        $this->assertFalse($filter->shouldCapture(new RuntimeException('Domain error', 9001), ExceptionFilterContext::reporter()));
    }

    public function testIgnoresByMessagePattern(): void
    {
        $filter = new ConfiguredExceptionFilter(ignoredMessagePatterns: [
            '/token expired/i',
        ]);

        $this->assertFalse($filter->shouldCapture(new RuntimeException('Access token expired'), ExceptionFilterContext::reporter()));
    }

    public function testContextFactoriesExposeSourceData(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $httpContext = ExceptionFilterContext::http($request);
        $this->assertSame(ExceptionFilterContext::SOURCE_HTTP, $httpContext->source);
        $this->assertSame($request, $httpContext->request);

        $consoleContext = ExceptionFilterContext::console('app:sync', 1);
        $this->assertSame(ExceptionFilterContext::SOURCE_CONSOLE, $consoleContext->source);
        $this->assertSame('app:sync', $consoleContext->consoleCommand);
        $this->assertSame(1, $consoleContext->consoleExitCode);

        $reporterContext = ExceptionFilterContext::reporter([
            'job_id' => 'job-123',
        ]);
        $this->assertSame(ExceptionFilterContext::SOURCE_REPORTER, $reporterContext->source);
        $this->assertSame([
            'job_id' => 'job-123',
        ], $reporterContext->metadata);
    }
}

final class HttpStatusException extends RuntimeException
{
    public function __construct(private readonly int|string $statusCode)
    {
        parent::__construct('HTTP failure');
    }

    public function getStatusCode(): int|string
    {
        return $this->statusCode;
    }
}
