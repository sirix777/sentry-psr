<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Reporter;

use Sentry\Breadcrumb;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Sirix\SentryPsr\ExceptionFilter\ExceptionFilterContext;
use Sirix\SentryPsr\ExceptionFilter\ExceptionFilterInterface;
use Throwable;

final readonly class SentryReporter
{
    public function __construct(private HubInterface $hub, private ?ExceptionFilterInterface $exceptionFilter = null) {}

    /**
     * @param array<string, mixed> $context
     */
    public function captureException(Throwable $throwable, array $context = []): void
    {
        if (! $this->shouldCaptureException($throwable, ExceptionFilterContext::reporter($context))) {
            return;
        }

        $this->hub->withScope(function($scope) use ($throwable, $context): void {
            if ([] !== $context) {
                $scope->setContext('additional_context', $context);
            }

            $scope->setLevel(Severity::error());

            $this->hub->captureException($throwable);
        });
    }

    /**
     * @param array<string, mixed> $context
     */
    public function captureMessage(string $message, string $level = 'error', array $context = []): void
    {
        $severity = match ($level) {
            'debug'   => Severity::debug(),
            'info'    => Severity::info(),
            'warning' => Severity::warning(),
            'fatal'   => Severity::fatal(),
            default   => Severity::error(),
        };

        $this->hub->withScope(function($scope) use ($message, $severity, $context): void {
            if ([] !== $context) {
                $scope->setContext('additional_context', $context);
            }

            $scope->setLevel($severity);

            $this->hub->captureMessage($message);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addBreadcrumb(string $message, string $category = 'custom', string $level = 'info', array $data = []): void
    {
        $breadcrumbLevel = match ($level) {
            'debug'   => Breadcrumb::LEVEL_DEBUG,
            'warning' => Breadcrumb::LEVEL_WARNING,
            'error'   => Breadcrumb::LEVEL_ERROR,
            'fatal'   => Breadcrumb::LEVEL_FATAL,
            default   => Breadcrumb::LEVEL_INFO,
        };

        $this->hub->addBreadcrumb(new Breadcrumb(
            level: $breadcrumbLevel,
            type: Breadcrumb::TYPE_DEFAULT,
            category: $category,
            message: $message,
            metadata: $data,
        ));
    }

    /**
     * @param array<string, mixed> $user
     */
    public function setUser(array $user): void
    {
        $this->hub->configureScope(static function($scope) use ($user): void {
            $scope->setUser($user);
        });
    }

    public function setTag(string $key, string $value): void
    {
        $this->hub->configureScope(static function($scope) use ($key, $value): void {
            $scope->setTag($key, $value);
        });
    }

    /**
     * @param array<string, mixed> $context
     */
    public function setContext(string $key, array $context): void
    {
        $this->hub->configureScope(static function($scope) use ($key, $context): void {
            $scope->setContext($key, $context);
        });
    }

    private function shouldCaptureException(Throwable $throwable, ExceptionFilterContext $exceptionFilterContext): bool
    {
        return ! $this->exceptionFilter instanceof ExceptionFilterInterface || $this->exceptionFilter->shouldCapture($throwable, $exceptionFilterContext);
    }
}
