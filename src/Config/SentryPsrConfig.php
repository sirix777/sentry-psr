<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Config;

use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;
use Sirix\ContainerResolver\Exception\MissingConfigValueException;

use function preg_match;
use function restore_error_handler;
use function set_error_handler;

final readonly class SentryPsrConfig
{
    /**
     * @throws MissingConfigValueException
     * @throws InvalidConfigValueException
     */
    public static function assertConfigured(ConfigReader $configReader): void
    {
        $configReader->requiredArray('sentry_psr');

        foreach ([
            'isolate_http_scope',
            'isolate_console_scope',
            'set_current_hub',
            'default_integrations',
            'flush_on_http_error',
            'flush_on_console_terminate',
            'capture_http_request_context',
            'capture_console_input',
        ] as $key) {
            $configReader->requiredBool('sentry_psr.' . $key);
        }

        self::requiredNonNegativeInt($configReader, 'sentry_psr.flush_timeout');

        $configReader->requiredArray('sentry_psr.redaction');
        $configReader->requiredString('sentry_psr.redaction.replacement');
        self::assertRegexPattern(
            'sentry_psr.redaction.sensitive_key_pattern',
            $configReader->requiredString('sentry_psr.redaction.sensitive_key_pattern'),
        );
        self::requiredNonNegativeInt($configReader, 'sentry_psr.redaction.max_depth');
        self::requiredNonNegativeInt($configReader, 'sentry_psr.redaction.max_items_per_container');
        self::requiredNonNegativeInt($configReader, 'sentry_psr.redaction.max_total_nodes');

        $configReader->requiredArray('sentry_psr.http_context');
        $configReader->requiredBool('sentry_psr.http_context.enabled');
        $configReader->requiredBool('sentry_psr.http_context.capture_headers');
        $configReader->requiredBool('sentry_psr.http_context.capture_query_string');
        $configReader->requiredStringList('sentry_psr.http_context.allowed_headers');
        $configReader->requiredStringList('sentry_psr.http_context.request_id_headers');
        $configReader->requiredStringList('sentry_psr.http_context.request_id_attributes');
        $configReader->requiredStringList('sentry_psr.http_context.allowed_attributes');
    }

    /**
     * @throws MissingConfigValueException
     * @throws InvalidConfigValueException
     */
    private static function requiredNonNegativeInt(ConfigReader $configReader, string $path): int
    {
        $value = $configReader->requiredInt($path);
        if ($value < 0) {
            throw InvalidConfigValueException::forType($path, 'int >= 0', $value, self::class);
        }

        return $value;
    }

    /**
     * @throws InvalidConfigValueException
     */
    private static function assertRegexPattern(string $path, string $pattern): void
    {
        set_error_handler(static fn (): bool => true);

        try {
            $isValid = false !== preg_match($pattern, '');
        } finally {
            restore_error_handler();
        }

        if (! $isValid) {
            throw InvalidConfigValueException::forType($path, 'valid regex pattern', $pattern, self::class);
        }
    }
}
