<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Reporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\Breadcrumb;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sirix\SentryPsr\ExceptionFilter\ExceptionFilterContext;
use Sirix\SentryPsr\ExceptionFilter\ExceptionFilterInterface;
use Sirix\SentryPsr\Reporter\SentryReporter;

/**
 * @internal
 */
#[CoversClass(SentryReporter::class)]
final class SentryReporterTest extends TestCase
{
    public function testCaptureExceptionUsesIsolatedScopeWithContextAndErrorLevel(): void
    {
        $exception = new RuntimeException('Failure');
        $scope     = $this->createMock(Scope::class);
        $hub       = $this->createMock(HubInterface::class);

        $scope->expects($this->once())
            ->method('setContext')
            ->with('additional_context', [
                'job_id' => 'job-123',
            ])
        ;
        $scope->expects($this->once())
            ->method('setLevel')
            ->with($this->callback(static fn (Severity $severity): bool => Severity::ERROR === (string) $severity))
        ;

        $hub->expects($this->once())
            ->method('withScope')
            ->willReturnCallback(static function(callable $callback) use ($scope): void {
                $callback($scope);
            })
        ;
        $hub->expects($this->once())
            ->method('captureException')
            ->with($exception)
        ;

        (new SentryReporter($hub))->captureException($exception, [
            'job_id' => 'job-123',
        ]);
    }

    public function testCaptureExceptionSkipsIgnoredException(): void
    {
        $exception = new RuntimeException('Auth expired');
        $hub       = $this->createMock(HubInterface::class);
        $filter    = $this->createMock(ExceptionFilterInterface::class);

        $filter->expects($this->once())
            ->method('shouldCapture')
            ->with(
                $exception,
                $this->callback(static fn (ExceptionFilterContext $context): bool => ExceptionFilterContext::SOURCE_REPORTER === $context->source
                    && [
                        'job_id' => 'job-123',
                    ] === $context->metadata)
            )
            ->willReturn(false)
        ;

        $hub->expects($this->never())->method('withScope');
        $hub->expects($this->never())->method('captureException');

        (new SentryReporter($hub, $filter))->captureException($exception, [
            'job_id' => 'job-123',
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('messageLevels')]
    public function testCaptureMessageMapsSeverityAndUsesIsolatedScope(string $level, string $expectedSeverity, array $context): void
    {
        $scope = $this->createMock(Scope::class);
        $hub   = $this->createMock(HubInterface::class);

        if ([] === $context) {
            $scope->expects($this->never())->method('setContext');
        } else {
            $scope->expects($this->once())
                ->method('setContext')
                ->with('additional_context', $context)
            ;
        }

        $scope->expects($this->once())
            ->method('setLevel')
            ->with($this->callback(static fn (Severity $severity): bool => $expectedSeverity === (string) $severity))
        ;

        $hub->expects($this->once())
            ->method('withScope')
            ->willReturnCallback(static function(callable $callback) use ($scope): void {
                $callback($scope);
            })
        ;
        $hub->expects($this->once())
            ->method('captureMessage')
            ->with('Something happened')
        ;

        (new SentryReporter($hub))->captureMessage('Something happened', $level, $context);
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>}>
     */
    public static function messageLevels(): iterable
    {
        yield 'debug' => ['debug', Severity::DEBUG, []];

        yield 'info' => ['info', Severity::INFO, []];

        yield 'warning' => ['warning', Severity::WARNING, []];

        yield 'fatal' => ['fatal', Severity::FATAL, []];

        yield 'unknown defaults to error' => [
            'notice', Severity::ERROR, [
                'extra' => true,
            ]];
    }

    #[DataProvider('breadcrumbLevels')]
    public function testAddBreadcrumbMapsLevel(string $level, string $expectedLevel): void
    {
        $hub = $this->createMock(HubInterface::class);

        $hub->expects($this->once())
            ->method('addBreadcrumb')
            ->with($this->callback(static function(Breadcrumb $breadcrumb) use ($expectedLevel): bool {
                return $expectedLevel === $breadcrumb->getLevel()
                    && Breadcrumb::TYPE_DEFAULT === $breadcrumb->getType()
                    && 'worker' === $breadcrumb->getCategory()
                    && 'Step finished' === $breadcrumb->getMessage()
                    && [
                        'step' => 2,
                    ] === $breadcrumb->getMetadata();
            }))
        ;

        (new SentryReporter($hub))->addBreadcrumb('Step finished', 'worker', $level, [
            'step' => 2,
        ]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function breadcrumbLevels(): iterable
    {
        yield 'debug' => ['debug', Breadcrumb::LEVEL_DEBUG];

        yield 'warning' => ['warning', Breadcrumb::LEVEL_WARNING];

        yield 'error' => ['error', Breadcrumb::LEVEL_ERROR];

        yield 'fatal' => ['fatal', Breadcrumb::LEVEL_FATAL];

        yield 'unknown defaults to info' => ['notice', Breadcrumb::LEVEL_INFO];
    }

    public function testSetUserConfiguresCurrentScope(): void
    {
        $hub = $this->createMock(HubInterface::class);

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function(callable $callback): bool {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->once())
                    ->method('setUser')
                    ->with([
                        'id' => 'user-123',
                    ])
                ;

                $callback($scope);

                return true;
            }))
        ;

        (new SentryReporter($hub))->setUser([
            'id' => 'user-123',
        ]);
    }

    public function testSetTagConfiguresCurrentScope(): void
    {
        $hub = $this->createMock(HubInterface::class);

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function(callable $callback): bool {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->once())->method('setTag')->with('tenant', 'acme');

                $callback($scope);

                return true;
            }))
        ;

        (new SentryReporter($hub))->setTag('tenant', 'acme');
    }

    public function testSetContextConfiguresCurrentScope(): void
    {
        $hub = $this->createMock(HubInterface::class);

        $hub->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function(callable $callback): bool {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->once())
                    ->method('setContext')
                    ->with('worker', [
                        'queue' => 'default',
                    ])
                ;

                $callback($scope);

                return true;
            }))
        ;

        (new SentryReporter($hub))->setContext('worker', [
            'queue' => 'default',
        ]);
    }
}
