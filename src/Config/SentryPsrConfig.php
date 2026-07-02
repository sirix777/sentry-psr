<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Config;

use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;
use Sirix\ContainerResolver\Exception\MissingConfigValueException;

use function array_key_exists;
use function class_exists;
use function in_array;
use function interface_exists;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;

final readonly class SentryPsrConfig
{
    /**
     * @var non-empty-list<string>
     */
    private const REDACTION_RULE_TYPES = [
        'fixed_value',
        'full_mask',
        'start_end',
        'unicode_start_end',
        'email',
        'phone',
        'name',
        'null',
        'offset',
    ];

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
            'log_console_command_start',
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
        $configReader->requiredBool('sentry_psr.redaction.use_default_rules');
        self::assertRuleMap('sentry_psr.redaction.rules', $configReader->requiredMap('sentry_psr.redaction.rules'));
        self::assertRegexRules('sentry_psr.redaction.regex_rules', $configReader->requiredList('sentry_psr.redaction.regex_rules'));

        $configReader->requiredArray('sentry_psr.http_context');
        $configReader->requiredBool('sentry_psr.http_context.enabled');
        $configReader->requiredBool('sentry_psr.http_context.capture_headers');
        $configReader->requiredBool('sentry_psr.http_context.capture_query_string');
        $configReader->requiredStringList('sentry_psr.http_context.allowed_headers');
        $configReader->requiredStringList('sentry_psr.http_context.request_id_headers');
        $configReader->requiredStringList('sentry_psr.http_context.request_id_attributes');
        $configReader->requiredStringList('sentry_psr.http_context.allowed_attributes');

        if ($configReader->has('sentry_psr.exception_filter')) {
            self::assertExceptionFilter($configReader);
        }
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
     * @param array<string, mixed> $rules
     *
     * @throws InvalidConfigValueException
     */
    private static function assertRuleMap(string $path, array $rules): void
    {
        foreach ($rules as $key => $ruleConfig) {
            self::assertRuleConfig($path . '.' . $key, $ruleConfig);
        }
    }

    /**
     * @param list<mixed> $regexRules
     *
     * @throws InvalidConfigValueException
     */
    private static function assertRegexRules(string $path, array $regexRules): void
    {
        foreach ($regexRules as $index => $regexRuleConfig) {
            $itemPath = $path . '.' . $index;
            if (! is_array($regexRuleConfig)) {
                throw InvalidConfigValueException::forType($itemPath, 'array', $regexRuleConfig, self::class);
            }

            if (! array_key_exists('pattern', $regexRuleConfig) || ! is_string($regexRuleConfig['pattern'])) {
                throw InvalidConfigValueException::forType($itemPath . '.pattern', 'string', $regexRuleConfig['pattern'] ?? null, self::class);
            }

            self::assertRegexPattern($itemPath . '.pattern', $regexRuleConfig['pattern']);

            if (! array_key_exists('rule', $regexRuleConfig)) {
                throw InvalidConfigValueException::forType($itemPath . '.rule', 'array', null, self::class);
            }

            self::assertRuleConfig($itemPath . '.rule', $regexRuleConfig['rule']);
        }
    }

    /**
     * @throws InvalidConfigValueException
     */
    private static function assertExceptionFilter(ConfigReader $configReader): void
    {
        $configReader->requiredMap('sentry_psr.exception_filter');
        $configReader->bool('sentry_psr.exception_filter.enabled', true);

        foreach ($configReader->stringList('sentry_psr.exception_filter.ignore_classes', []) as $index => $ignoredClass) {
            if (! class_exists($ignoredClass) && ! interface_exists($ignoredClass)) {
                throw InvalidConfigValueException::forType(
                    'sentry_psr.exception_filter.ignore_classes.' . $index,
                    'existing class or interface',
                    $ignoredClass,
                    self::class,
                );
            }
        }

        self::assertIntList(
            'sentry_psr.exception_filter.ignore_http_statuses',
            $configReader->list('sentry_psr.exception_filter.ignore_http_statuses', []),
        );
        self::assertIntList(
            'sentry_psr.exception_filter.ignore_codes',
            $configReader->list('sentry_psr.exception_filter.ignore_codes', []),
        );

        foreach ($configReader->stringList('sentry_psr.exception_filter.ignore_message_patterns', []) as $index => $pattern) {
            self::assertRegexPattern('sentry_psr.exception_filter.ignore_message_patterns.' . $index, $pattern);
        }

        $configReader->bool('sentry_psr.exception_filter.inspect_previous', true);
    }

    /**
     * @param list<mixed> $values
     *
     * @throws InvalidConfigValueException
     */
    private static function assertIntList(string $path, array $values): void
    {
        foreach ($values as $index => $value) {
            if (! is_int($value)) {
                throw InvalidConfigValueException::forType($path . '.' . $index, 'int', $value, self::class);
            }
        }
    }

    /**
     * @throws InvalidConfigValueException
     */
    private static function assertRuleConfig(string $path, mixed $ruleConfig): void
    {
        if (! is_array($ruleConfig)) {
            throw InvalidConfigValueException::forType($path, 'array', $ruleConfig, self::class);
        }

        if (! array_key_exists('type', $ruleConfig) || ! is_string($ruleConfig['type'])) {
            throw InvalidConfigValueException::forType($path . '.type', 'string', $ruleConfig['type'] ?? null, self::class);
        }

        if (! in_array($ruleConfig['type'], self::REDACTION_RULE_TYPES, true)) {
            throw InvalidConfigValueException::forAllowedValues($path . '.type', self::REDACTION_RULE_TYPES, $ruleConfig['type'], self::class);
        }

        match ($ruleConfig['type']) {
            'fixed_value'                    => self::assertRuleString($path, $ruleConfig, 'value'),
            'start_end', 'unicode_start_end' => self::assertStartEndRule($path, $ruleConfig),
            'offset'                         => self::assertRuleInt($path, $ruleConfig, 'offset'),
            default                          => null,
        };
    }

    /**
     * @param array<string, mixed> $ruleConfig
     *
     * @throws InvalidConfigValueException
     */
    private static function assertStartEndRule(string $path, array $ruleConfig): void
    {
        self::assertRuleNonNegativeInt($path, $ruleConfig, 'start');
        self::assertRuleNonNegativeInt($path, $ruleConfig, 'end');
    }

    /**
     * @param array<string, mixed> $ruleConfig
     *
     * @throws InvalidConfigValueException
     */
    private static function assertRuleString(string $path, array $ruleConfig, string $key): void
    {
        if (! array_key_exists($key, $ruleConfig) || ! is_string($ruleConfig[$key])) {
            throw InvalidConfigValueException::forType($path . '.' . $key, 'string', $ruleConfig[$key] ?? null, self::class);
        }
    }

    /**
     * @param array<string, mixed> $ruleConfig
     *
     * @throws InvalidConfigValueException
     */
    private static function assertRuleInt(string $path, array $ruleConfig, string $key): void
    {
        if (! array_key_exists($key, $ruleConfig) || ! is_int($ruleConfig[$key])) {
            throw InvalidConfigValueException::forType($path . '.' . $key, 'int', $ruleConfig[$key] ?? null, self::class);
        }
    }

    /**
     * @param array<string, mixed> $ruleConfig
     *
     * @throws InvalidConfigValueException
     */
    private static function assertRuleNonNegativeInt(string $path, array $ruleConfig, string $key): void
    {
        self::assertRuleInt($path, $ruleConfig, $key);

        if ($ruleConfig[$key] < 0) {
            throw InvalidConfigValueException::forType($path . '.' . $key, 'int >= 0', $ruleConfig[$key], self::class);
        }
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
