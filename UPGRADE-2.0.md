# Upgrade from 1.x to 2.0

Version 2.0 makes `sirix/sentry-psr` safe for long-running PHP applications by default. The release intentionally contains breaking changes around scope lifecycle, hub creation, DI services and dependency declarations.

## Breaking changes

### Scope isolation is enabled by default

HTTP requests and console commands now run inside isolated Sentry scopes.

Old behavior:

```text
Scope data could live for the whole PHP process.
```

New behavior:

```text
Request/command/job-specific user, tags, contexts and breadcrumbs are removed when the lifecycle ends.
```

Migration:

- do not rely on request/command-local tags or breadcrumbs being available after the request/command;
- set truly global tags at bootstrap level or through Sentry SDK options;
- set request/job-specific data inside the HTTP middleware, console command or manual lifecycle callback.

### Hub creation is explicit

`SentryHubFactory` no longer calls `Sentry\init()` as the only integration path. It creates a `Hub` explicitly through `ClientBuilder`.

```php
$client = ClientBuilder::create($config)->getClient();
$hub = new Hub($client);
```

`SentrySdk::setCurrentHub($hub)` is only called when `sentry_psr.set_current_hub=true`.

Migration:

- prefer injecting `Sentry\State\HubInterface` or `Sirix\SentryPsr\Reporter\SentryReporter` from the container;
- keep `sentry_psr.set_current_hub=true` if your application uses Sentry global functions;
- set `sentry_psr.set_current_hub=false` for explicit DI-only usage.

### Required `sentry_psr` configuration section

`ConfigProvider` registers services only. Add the package-level config section by copying the baseline config from `src/config/sentry.global.php` into your application config:

```php
return [
    'sentry' => [
        'dsn' => 'https://<key>@sentry.io/<project>',
    ],

    'sentry_psr' => [
        'isolate_http_scope' => true,
        'isolate_console_scope' => true,
        'set_current_hub' => true,
        'default_integrations' => false,
        'flush_on_http_error' => false,
        'flush_on_console_terminate' => true,
        'flush_timeout' => 2,
        'capture_http_request_context' => true,
        'capture_console_input' => true,
        'log_console_command_start' => true,
        'redaction' => [
            'replacement' => '[Filtered]',
            'sensitive_key_pattern' => '/password|passwd|secret|token|api[_-]?key|authorization|cookie/i',
            'max_depth' => 8,
            'max_items_per_container' => 100,
            'max_total_nodes' => 5000,
            'use_default_rules' => false,
            'rules' => [],
            'regex_rules' => [],
        ],
        'http_context' => [
            'enabled' => true,
            'capture_headers' => false,
            'capture_query_string' => false,
            'allowed_headers' => [
                'User-Agent',
                'X-Request-Id',
            ],
            'request_id_headers' => [
                'X-Request-Id',
                'X-Correlation-Id',
            ],
            'request_id_attributes' => [
                'request_id',
                'requestId',
                'correlation_id',
                'correlationId',
            ],
            'allowed_attributes' => [
                'route',
                'route_name',
                'request_id',
                'correlation_id',
            ],
        ],
    ],
];
```

Console input redaction is configured through `sentry_psr.redaction` and uses a Sentry-specific `sirix/redaction` redactor instance. The listener does not reuse a generic application-wide `Sirix\Redaction\RedactorInterface` service if one is present in the container. Use `rules` and `regex_rules` under `sentry_psr.redaction` for additional exact-key or regex-key filters.

Missing `sentry_psr` or missing required keys fail during factory creation with `sirix/container-resolver` config exceptions. `default_integrations=false` remains the safe default. Enable it explicitly if you want standard Sentry SDK global handlers/integrations.

### Constructor signatures changed

Manual construction with the old positional logger argument no longer works:

```php
new SentryErrorMiddleware($hub, $logger); // 1.x style
new SentryCommandListener($hub, $logger); // 1.x style
```

Use named arguments or container factories instead:

```php
new SentryErrorMiddleware($hub, logger: $logger);
new SentryCommandListener($hub, logger: $logger);
```

### Static helper was removed

Old:

```php
use Sirix\SentryPsr\Helper\SentryHelper;

SentryHelper::setUser(['id' => '123']);
SentryHelper::captureException($exception);
```

New:

```php
use Sirix\SentryPsr\Reporter\SentryReporter;

final readonly class Service
{
    public function __construct(private SentryReporter $sentry) {}

    public function run(): void
    {
        $this->sentry->setUser(['id' => '123']);
        $this->sentry->captureException($exception);
    }
}
```

`Sirix\SentryPsr\Helper\SentryHelper` and `SentryHelperFactory` are removed in 2.0. The container no longer registers `SentryHelper::class`; inject `SentryReporter::class` instead.

### Factories use `sirix/container-resolver`

Factories now use `ContainerResolver` and `ConfigReader` for strict PSR-11 service resolution and config reading.

Migration:

- ensure your container exposes `config` as an array if present;
- ensure `sentry` and `sentry_psr` values use the documented types;
- expect explicit container/config exceptions for invalid values instead of silent fallback behavior.

### Optional dependencies remain optional

Core runtime dependencies include the HTTP/container/Sentry integration path and the PSR-3 logger interface:

- `sirix/container-resolver`
- `psr/http-server-handler`
- `psr/log`

Concrete logger implementations and console integrations remain optional and are not installed for HTTP-only projects:

- `monolog/monolog`
- `psr/event-dispatcher`
- `symfony/console`
- `symfony/event-dispatcher`

Install them only when you use the corresponding integration. `composer analyse-deps` is configured to keep checking core code while allowing the dedicated optional Symfony integration classes.

## Checklist

1. Add or review the `sentry_psr` config section.
2. Decide whether `sentry_psr.set_current_hub` should be `true` or `false`.
3. Replace static `SentryHelper` usage with injectable `SentryReporter`.
4. Ensure `SentryErrorMiddleware` is early enough in the HTTP pipeline to wrap app code.
5. For console applications, ensure `ConsoleEvents::TERMINATE` is dispatched so the command scope is popped and flushed.
6. Review HTTP header capture and console input capture for sensitive data.
7. If using buffered/custom transports, tune `sentry_psr.flush_timeout` and flush lifecycle hooks.
8. Run:

```bash
composer validate --strict
composer analyse-deps
composer check
```
