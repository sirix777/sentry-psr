<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\ExceptionFilter;

use Throwable;

use function in_array;
use function is_int;
use function is_string;
use function method_exists;
use function preg_match;

final readonly class ConfiguredExceptionFilter implements ExceptionFilterInterface
{
    /**
     * @param list<string> $ignoredClasses
     * @param list<int>    $ignoredHttpStatuses
     * @param list<int>    $ignoredCodes
     * @param list<string> $ignoredMessagePatterns
     */
    public function __construct(
        private bool $enabled = true,
        private array $ignoredClasses = [],
        private array $ignoredHttpStatuses = [],
        private array $ignoredCodes = [],
        private array $ignoredMessagePatterns = [],
        private bool $inspectPrevious = true,
    ) {}

    public static function allowAll(): self
    {
        return new self(enabled: false);
    }

    public function shouldCapture(Throwable $throwable, ExceptionFilterContext $exceptionFilterContext): bool
    {
        if (! $this->enabled) {
            return true;
        }

        $current = $throwable;
        do {
            if ($this->matches($current)) {
                return false;
            }

            $current = $this->inspectPrevious ? $current->getPrevious() : null;
        } while ($current instanceof Throwable);

        return true;
    }

    private function matches(Throwable $throwable): bool
    {
        foreach ($this->ignoredClasses as $ignoredClass) {
            if ($throwable instanceof $ignoredClass) {
                return true;
            }
        }

        $httpStatus = $this->httpStatusCode($throwable);
        if (null !== $httpStatus && in_array($httpStatus, $this->ignoredHttpStatuses, true)) {
            return true;
        }

        if (in_array($throwable->getCode(), $this->ignoredCodes, true)) {
            return true;
        }

        foreach ($this->ignoredMessagePatterns as $ignoredMessagePattern) {
            if (1 === preg_match($ignoredMessagePattern, $throwable->getMessage())) {
                return true;
            }
        }

        return false;
    }

    private function httpStatusCode(Throwable $throwable): ?int
    {
        foreach (['getStatusCode', 'getStatus'] as $method) {
            if (! method_exists($throwable, $method)) {
                continue;
            }

            try {
                $status = $throwable->{$method}();
            } catch (Throwable) {
                continue;
            }

            $status = $this->normalizeHttpStatusCode($status);
            if (null !== $status) {
                return $status;
            }
        }

        return $this->normalizeHttpStatusCode($throwable->getCode());
    }

    private function normalizeHttpStatusCode(mixed $status): ?int
    {
        if (is_string($status) && 1 === preg_match('/^\d+$/', $status)) {
            $status = (int) $status;
        }

        if (! is_int($status)) {
            return null;
        }

        if ($status < 100 || $status > 599) {
            return null;
        }

        return $status;
    }
}
