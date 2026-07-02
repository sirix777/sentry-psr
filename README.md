# sirix/sentry-psr

[![Latest Stable Version](http://poser.pugx.org/sirix/sentry-psr/v)](https://packagist.org/packages/sirix/sentry-psr) [![Total Downloads](http://poser.pugx.org/sirix/sentry-psr/downloads)](https://packagist.org/packages/sirix/sentry-psr) [![Latest Unstable Version](http://poser.pugx.org/sirix/sentry-psr/v/unstable)](https://packagist.org/packages/sirix/sentry-psr) [![License](http://poser.pugx.org/sirix/sentry-psr/license)](https://packagist.org/packages/sirix/sentry-psr) [![PHP Version Require](http://poser.pugx.org/sirix/sentry-psr/require/php)](https://packagist.org/packages/sirix/sentry-psr)

PSR-15 & PSR-11 integration for Sentry with long-running-safe HTTP and console lifecycle handling.

This library provides:

- `SentryErrorMiddleware` for PSR-15 HTTP pipelines.
- `SentryHubFactory` for explicit Sentry hub creation from PSR-11 config.
- `SentryLifecycle` for isolated scope and flush lifecycle operations.
- `SentryReporter` as the recommended injectable API for capturing events and enriching scope.
- `SentryCommandListener` and `ConsoleEventDispatcherFactory` for Symfony Console/Laminas CLI integration.
- A Mezzio/Laminas-friendly `ConfigProvider`.

## Requirements

- PHP `~8.2 || ~8.3 || ~8.4 || ~8.5`
- `sentry/sentry` `^4.0`
- Core PSR packages for container, HTTP messages and HTTP middleware/handler.

Logger integration uses the required PSR-3 `psr/log` interface. Concrete logger implementations such as `monolog/monolog` remain optional.

Optional integrations are intentionally not required for HTTP-only projects:

- `monolog/monolog` as a logger implementation/service.
- `symfony/console` and `symfony/event-dispatcher` for console command capture.
- `psr/event-dispatcher` if your container/framework consumes the PSR event dispatcher alias.

## Installation

```bash
composer require sirix/sentry-psr
```

## Configuration

Use `sentry` for native Sentry SDK options and `sentry_psr` for this package's integration/lifecycle behavior.

`sentry_psr` is required at runtime. Copy the provided baseline config from `src/config/sentry.global.php` into your application config, then adjust it for your project. The `ConfigProvider` wires services only; it does not inject runtime defaults.

```php
return [
    'sentry' => [
        'dsn' => 'https://<key>@sentry.io/<project>',
        'environment' => 'production',
        'release' => '1.2.3',
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
        'exception_filter' => [
            'enabled' => true,
            'ignore_classes' => [],
            'ignore_http_statuses' => [],
            'ignore_codes' => [],
            'ignore_message_patterns' => [],
            'inspect_previous' => true,
        ],
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

### `sentry` vs `sentry_psr`

- `sentry` is passed to `Sentry\ClientBuilder` and supports native Sentry PHP SDK options.
- `sentry_psr` controls this package: scope isolation, global hub behavior, flush timing and HTTP/console context enrichment.

Invalid container services, missing `sentry_psr`, or invalid config value types are reported through `sirix/container-resolver` exceptions, so misconfiguration fails early and with context.

### Exception filtering

Use `sentry_psr.exception_filter` to suppress expected exceptions before this package calls `captureException()`. Filtered exceptions are still rethrown by the HTTP middleware and still flow through Symfony Console, but they are not sent to Sentry, not logged by this package as captured errors, and do not trigger the HTTP/error flush path.

```php
'exception_filter' => [
    'enabled' => true,
    'ignore_classes' => [
        AuthExpiredException::class,
        UnauthorizedException::class,
    ],
    'ignore_http_statuses' => [
        401,
    ],
    'ignore_codes' => [],
    'ignore_message_patterns' => [
        '/token expired/i',
    ],
    'inspect_previous' => true,
],
```

`ignore_classes` matches class and interface inheritance and every entry must resolve to an existing class or interface during config validation. `ignore_http_statuses` checks `getStatusCode()`, `getStatus()` and then the throwable code when it looks like an HTTP status code. `ignore_codes` checks the raw throwable code. `ignore_message_patterns` accepts valid regex patterns. When `inspect_previous=true`, the same rules are applied to the previous-exception chain.

The configured filter is created by this package's HTTP, console and reporter factories. For domain-specific logic, register a `Sirix\SentryPsr\ExceptionFilter\ExceptionFilterInterface` service in your container:

```php
use Sirix\SentryPsr\ExceptionFilter\ExceptionFilterContext;
use Sirix\SentryPsr\ExceptionFilter\ExceptionFilterInterface;

final readonly class AuthExceptionFilter implements ExceptionFilterInterface
{
    public function shouldCapture(\Throwable $throwable, ExceptionFilterContext $context): bool
    {
        if ($throwable instanceof AuthExpiredException) {
            return false;
        }

        if (
            ExceptionFilterContext::SOURCE_HTTP === $context->source
            && 401 === $throwable->getCode()
        ) {
            return false;
        }

        return true;
    }
}
```

### Console input redaction

Console command arguments and options are redacted with a Sentry-specific `sirix/redaction` redactor configured from `sentry_psr.redaction` before they are attached to Sentry command context. The listener intentionally does not reuse an application-wide `Sirix\Redaction\RedactorInterface` service, so Sentry console sanitization remains isolated and predictable.

By default, keys matching `password|passwd|secret|token|api[_-]?key|authorization|cookie` are replaced with `[Filtered]`, recursively and with traversal limits suitable for long-running workers. You can opt in to additional exact-key and regex-key redaction rules:

```php
'redaction' => [
    'replacement' => '*',
    'sensitive_key_pattern' => '/password|passwd|secret|token|api[_-]?key|authorization|cookie/i',
    'max_depth' => 8,
    'max_items_per_container' => 100,
    'max_total_nodes' => 5000,
    'use_default_rules' => false,
    'rules' => [
        'email' => ['type' => 'email'],
        'phone' => ['type' => 'phone'],
        'card_number' => ['type' => 'start_end', 'start' => 6, 'end' => 4],
    ],
    'regex_rules' => [
        [
            'pattern' => '/customer[_-]?name/i',
            'rule' => ['type' => 'name'],
        ],
    ],
],
```

Supported rule types are `fixed_value`, `full_mask`, `start_end`, `unicode_start_end`, `email`, `phone`, `name`, `null` and `offset`. `use_default_rules=false` is the baseline because the default `sirix/redaction` rules are intentionally broad and may hide more Sentry context than expected.

HTTP filtering is intentionally separate: request headers still use the `http_context` allowlist/blocklist model and are not controlled by console redaction settings. Raw query strings and request targets are not captured unless `sentry_psr.http_context.capture_query_string=true`.

### Default integrations

`default_integrations` defaults to `false` through `sentry_psr.default_integrations` unless you explicitly set `sentry.default_integrations`.

This package intentionally avoids registering global SDK error/exception handlers by default so embedding applications and long-running workers remain in control of capture lifecycle. If you want the standard Sentry SDK integrations, opt in explicitly:

```php
'sentry_psr' => [
    'default_integrations' => true,
],
```

## DI Container wiring

`ConfigProvider` registers services only. Runtime options must be provided by your application config, usually by copying `src/config/sentry.global.php`.

`ConfigProvider` registers:

- `Sentry\State\HubInterface` → `Sirix\SentryPsr\Hub\SentryHubFactory`
- `Sirix\SentryPsr\Lifecycle\SentryLifecycle` → `Sirix\SentryPsr\Lifecycle\SentryLifecycleFactory`
- `Sirix\SentryPsr\Reporter\SentryReporter` → `Sirix\SentryPsr\Reporter\SentryReporterFactory`
- `Sirix\SentryPsr\Middleware\SentryErrorMiddleware` → `Sirix\SentryPsr\Middleware\SentryErrorMiddlewareFactory`

Console-related services are registered only when Symfony Console and EventDispatcher classes are available:

- `Sirix\SentryPsr\Listener\SentryCommandListener` → `Sirix\SentryPsr\Listener\SentryCommandListenerFactory`
- `Symfony\Component\EventDispatcher\EventDispatcher` → `Sirix\SentryPsr\ConsoleEventDispatcher\ConsoleEventDispatcherFactory`

Aliases registered with console integration:

- `Psr\EventDispatcher\EventDispatcherInterface` ⇒ `Symfony\Component\EventDispatcher\EventDispatcher`
- `Laminas\Cli\SymfonyEventDispatcher` ⇒ `Symfony\Component\EventDispatcher\EventDispatcher`

## Long-running applications

When using this package in long-running PHP processes, make sure every request, job or command is executed inside an isolated Sentry scope. Otherwise user, tag, context and breadcrumb data may leak between independent units of work.

Version 2 behavior is long-running safe by default:

- each HTTP request runs inside `HubInterface::withScope()`;
- each console command pushes a scope on `ConsoleEvents::COMMAND` and pops it on `ConsoleEvents::TERMINATE`;
- `SentryLifecycle::flush()` provides an explicit flush hook for buffered/custom transports;
- `SentryReporter` mutates the current request/command-local scope when called inside that lifecycle.

### HTTP pipeline placement

Place `SentryErrorMiddleware` as early as possible so it wraps application middleware that might add Sentry context:

```php
use Psr\Container\ContainerInterface;
use Sirix\SentryPsr\Middleware\SentryErrorMiddleware;

return function (App $app, ContainerInterface $container): void {
    $app->pipe(SentryErrorMiddleware::class);

    // routes and application middleware after Sentry
};
```

If the middleware is not outermost, only downstream middleware/handlers are covered by the isolated scope.

### Mezzio under RoadRunner

After copying the baseline config, these are the typical long-running HTTP values to keep or review:

```php
return [
    'sentry_psr' => [
        'isolate_http_scope' => true,
        'flush_on_http_error' => false,
        'flush_timeout' => 2,
    ],
];
```

For async/buffered transports, call `SentryLifecycle::flush()` from your worker shutdown hook, or enable targeted flush points where needed.

### Queue consumer / manual job lifecycle

```php
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Reporter\SentryReporter;

final readonly class JobRunner
{
    public function __construct(
        private SentryLifecycle $lifecycle,
        private SentryReporter $reporter,
    ) {}

    public function run(Job $job): void
    {
        $this->lifecycle->withIsolatedScope(function () use ($job): void {
            $this->reporter->setTag('job', $job->name());
            $this->reporter->setContext('job', ['id' => $job->id()]);

            $job->handle();
        });

        $this->lifecycle->flush();
    }
}
```

## HTTP context and sensitive data

HTTP context enrichment is enabled by default and stores method, path, host, scheme, selected scalar attributes and request/correlation id. Raw query strings and request targets are excluded by default and require `sentry_psr.http_context.capture_query_string=true`.

Sensitive data is excluded by default:

- `Authorization`
- `Cookie`
- `Set-Cookie`
- raw request body
- arbitrary form fields

Headers are not captured unless `sentry_psr.http_context.capture_headers=true`, and even then only `allowed_headers` are included. Sensitive header names remain blocked.

## Console integration

Install the optional console dependencies before enabling this integration:

```bash
composer require symfony/console symfony/event-dispatcher
```

`SentryCommandListener` subscribes to:

- `ConsoleEvents::COMMAND` — push command-local scope, set command context and add breadcrumb;
- `ConsoleEvents::ERROR` — capture command exception in the active command scope;
- `ConsoleEvents::TERMINATE` — flush if configured and pop command scope.

Set `sentry_psr.log_console_command_start=false` to disable the PSR-3 `Console command started` info log while keeping command breadcrumbs and error reporting enabled.

Manual wiring:

```php
use Sentry\State\HubInterface;
use Sirix\SentryPsr\ConsoleEventDispatcher\ConsoleEventDispatcherFactory;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Listener\SentryCommandListener;
use Sirix\SentryPsr\Listener\SentryCommandListenerFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;

return [
    'dependencies' => [
        'factories' => [
            HubInterface::class => Sirix\SentryPsr\Hub\SentryHubFactory::class,
            SentryLifecycle::class => Sirix\SentryPsr\Lifecycle\SentryLifecycleFactory::class,
            SentryCommandListener::class => SentryCommandListenerFactory::class,
            EventDispatcher::class => ConsoleEventDispatcherFactory::class,
        ],
    ],
];
```

Console arguments/options are captured by default, but keys containing password, secret, token, api key, authorization or cookie are filtered.

## Reporter API

Prefer `SentryReporter` from your container:

```php
use Sirix\SentryPsr\Reporter\SentryReporter;

final readonly class CheckoutService
{
    public function __construct(private SentryReporter $sentry) {}

    public function checkout(): void
    {
        $this->sentry->setTag('feature', 'checkout');
        $this->sentry->setUser(['id' => '123']);

        try {
            // ...
        } catch (\Throwable $exception) {
            $this->sentry->captureException($exception, ['step' => 'payment']);
            throw $exception;
        }
    }
}
```

### Static helper removal

`Sirix\SentryPsr\Helper\SentryHelper` was removed in 2.0. Use `SentryReporter` through DI instead, so the hub and scope are explicit and safe for long-running applications.

## Global current hub

`SentryHubFactory` creates a hub explicitly through `ClientBuilder` and `Hub`.

By default `sentry_psr.set_current_hub=true`, so Sentry SDK global functions continue to use the configured hub. Set it to `false` if your application wants fully explicit DI-only hub usage:

```php
'sentry_psr' => [
    'set_current_hub' => false,
],
```

If disabled, code using `Sentry\captureException()` or `SentrySdk::getCurrentHub()` will not automatically use this package's hub.

## Migration

See [`UPGRADE-2.0.md`](UPGRADE-2.0.md) for breaking changes and migration steps from 1.x.

## Development

Useful Composer scripts:

- `composer validate --strict` – validate composer metadata and lock file
- `composer analyse-deps` – run dependency analyser
- `composer test` – run PHPUnit
- `composer cs-check` / `composer cs-fix` – code style
- `composer phpstan` – static analysis
- `composer rector` – automated refactoring (dry-run by default)
- `composer check` – run the local quality gate

## License

MIT © Sirix
