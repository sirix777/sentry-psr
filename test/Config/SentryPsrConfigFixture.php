<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Config;

use function array_replace_recursive;

final readonly class SentryPsrConfigFixture
{
    /**
     * @param array<string, mixed> $sentryPsrOverrides
     *
     * @return array{sentry_psr: array<string, mixed>}
     */
    public static function config(array $sentryPsrOverrides = []): array
    {
        return [
            'sentry_psr' => array_replace_recursive(self::sentryPsr(), $sentryPsrOverrides),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sentryPsr(): array
    {
        return [
            'isolate_http_scope'           => true,
            'isolate_console_scope'        => true,
            'set_current_hub'              => true,
            'default_integrations'         => false,
            'flush_on_http_error'          => false,
            'flush_on_console_terminate'   => true,
            'flush_timeout'                => 2,
            'capture_http_request_context' => true,
            'capture_console_input'        => true,
            'redaction'                    => [
                'replacement'             => '[Filtered]',
                'sensitive_key_pattern'   => '/password|passwd|secret|token|api[_-]?key|authorization|cookie/i',
                'max_depth'               => 8,
                'max_items_per_container' => 100,
                'max_total_nodes'         => 5000,
            ],
            'http_context'                 => [
                'enabled'               => true,
                'capture_headers'       => false,
                'capture_query_string'  => false,
                'allowed_headers'       => [
                    'User-Agent',
                    'X-Request-Id',
                ],
                'request_id_headers'    => [
                    'X-Request-Id',
                    'X-Correlation-Id',
                ],
                'request_id_attributes' => [
                    'request_id',
                    'requestId',
                    'correlation_id',
                    'correlationId',
                ],
                'allowed_attributes'    => [
                    'route',
                    'route_name',
                    'request_id',
                    'correlation_id',
                ],
            ],
        ];
    }
}
