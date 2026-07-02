<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\ExceptionFilter;

use Psr\Http\Message\ServerRequestInterface;

final readonly class ExceptionFilterContext
{
    public const SOURCE_HTTP     = 'http';
    public const SOURCE_CONSOLE  = 'console';
    public const SOURCE_REPORTER = 'reporter';

    /**
     * @param array<string, mixed> $metadata
     */
    private function __construct(
        public string $source,
        public ?ServerRequestInterface $request = null,
        public ?string $consoleCommand = null,
        public ?int $consoleExitCode = null,
        public array $metadata = [],
    ) {}

    public static function http(ServerRequestInterface $serverRequest): self
    {
        return new self(self::SOURCE_HTTP, request: $serverRequest);
    }

    public static function console(?string $command = null, ?int $exitCode = null): self
    {
        return new self(self::SOURCE_CONSOLE, consoleCommand: $command, consoleExitCode: $exitCode);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function reporter(array $metadata = []): self
    {
        return new self(self::SOURCE_REPORTER, metadata: $metadata);
    }
}
