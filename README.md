# sirix/sentry-psr
[![Latest Stable Version](http://poser.pugx.org/sirix/sentry-psr/v)](https://packagist.org/packages/sirix/sentry-psr) [![Total Downloads](http://poser.pugx.org/sirix/sentry-psr/downloads)](https://packagist.org/packages/sirix/sentry-psr) [![Latest Unstable Version](http://poser.pugx.org/sirix/sentry-psr/v/unstable)](https://packagist.org/packages/sirix/sentry-psr) [![License](http://poser.pugx.org/sirix/sentry-psr/license)](https://packagist.org/packages/sirix/sentry-psr) [![PHP Version Require](http://poser.pugx.org/sirix/sentry-psr/require/php)](https://packagist.org/packages/sirix/sentry-psr)

PSR-15 & PSR-11 integration for Sentry with optional console logging

This library provides:

- A PSR-15 middleware (`SentryErrorMiddleware`) that captures unhandled HTTP exceptions and forwards them to Sentry, with optional PSR-3 logging.
- A factory for initializing and retrieving the Sentry hub (`SentryHubFactory`) from your DI container using your `config['sentry']` settings.
- A small convenience helper (`SentryHelper`) with static methods to capture exceptions/messages, add breadcrumbs, set user/tags/context. Its factory wires the current `HubInterface` so the helper uses your configured hub.
- Optional console error listener (`SentryCommandListener`) for Symfony Console/Laminas CLI to capture exceptions in CLI commands.

It is framework-agnostic and works with any PSR-11 container and PSR-15 middleware pipeline. A Mezzio/Laminas-friendly `ConfigProvider` is included.

---

## Requirements

- PHP ~8.2 || ~8.3 || ~8.4 || ~8.5
- `sentry/sentry` ^4.0
- PSR packages as needed: `psr/container`, `psr/http-message`, `psr/http-server-middleware`
- Optional:
  - `psr/log` or `monolog/monolog` for logging alongside Sentry
  - `symfony/console` (and optionally `laminas/laminas-cli`) for console integration
  - `symfony/event-dispatcher` if you want the Symfony EventDispatcher service/aliases to be auto‑wired

## Installation

```bash
composer require sirix/sentry-psr
```

Make sure you also require and configure `sentry/sentry` per your needs. This package wires Sentry using your container config.

## Configuration

Provide your Sentry configuration under the `sentry` key in your container config (e.g., `config/autoload/*.php` for Mezzio/Laminas, or however you build your container). The array is passed to `Sentry\init()`.

Minimal example:

```php
return [
    'sentry' => [
        'dsn' => 'https://<key>@sentry.io/<project>',
        // Any options supported by Sentry PHP SDK, e.g.:
        // 'environment' => 'production',
        // 'release' => '1.2.3',
        // 'traces_sample_rate' => 0.1,
    ],
];
```

## DI Container wiring

This library exposes factories to a PSR-11 container:

- `Sentry\State\HubInterface` → `Sirix\SentryPsr\Hub\SentryHubFactory`
- `Sirix\SentryPsr\Middleware\SentryErrorMiddleware` → `Sirix\SentryPsr\Middleware\SentryErrorMiddlewareFactory`
- `Sirix\SentryPsr\Helper\SentryHelper` → `Sirix\SentryPsr\Helper\SentryHelperFactory`
- Optional console (see below):
  - `Sirix\SentryPsr\Listener\SentryCommandListener` → `Sirix\SentryPsr\Listener\SentryCommandListenerFactory`
  - `Symfony\Component\EventDispatcher\EventDispatcher` → `Sirix\SentryPsr\ConsoleEventDispatcher\ConsoleEventDispatcherFactory`

### Mezzio/Laminas projects

Add the `ConfigProvider` by installing the package. If you use Laminas Config Aggregator, this happens automatically via Composer extra metadata:

```json
{
  "extra": {
    "laminas": {
      "config-provider": "Sirix\\SentryPsr\\ConfigProvider"
    }
  }
}
```

The `ConfigProvider` registers factories for:

- `Sentry\State\HubInterface`
- `Sirix\SentryPsr\Middleware\SentryErrorMiddleware`
- `Sirix\SentryPsr\Helper\SentryHelper`

Notes:

- Console-related factories are auto-registered when `symfony/console` is installed (i.e., when `Symfony\Component\Console\Command\Command` exists). If `symfony/console` is not present or you need custom wiring, see the Console integration section.
- The Symfony EventDispatcher factory and aliases are registered only when `symfony/event-dispatcher` is installed (i.e., when `Symfony\Component\EventDispatcher\EventDispatcher` exists).

### Aliases added by ConfigProvider (when symfony/event-dispatcher is present)

When `symfony/event-dispatcher` is installed, the `ConfigProvider` also registers helpful service aliases so you can type-hint against PSR-14 and common Laminas CLI expectations:

- `Psr\EventDispatcher\EventDispatcherInterface` ⇒ `Symfony\Component\EventDispatcher\EventDispatcher`
- `'Laminas\Cli\SymfonyEventDispatcher'` ⇒ `Symfony\Component\EventDispatcher\EventDispatcher`

These aliases are only active when `Symfony\Component\EventDispatcher\EventDispatcher` is available. They map to the dispatcher created by `Sirix\SentryPsr\ConsoleEventDispatcher\ConsoleEventDispatcherFactory`.


## HTTP: SentryErrorMiddleware (PSR-15)

Place the middleware in your pipeline so that it wraps your application code. On any unhandled `Throwable`, it will:

- call `HubInterface::captureException($e)`
- optionally log the error via a PSR-3 `LoggerInterface` if present in the container
- rethrow the exception so your framework still handles the error response

Example (Mezzio `config/pipeline.php`):

```php
use Psr\Container\ContainerInterface;
use Sirix\SentryPsr\Middleware\SentryErrorMiddleware;

return function (App $app, ContainerInterface $container): void {
    // Put SentryErrorMiddleware early in the pipeline
    $app->pipe(SentryErrorMiddleware::class);

    // ... your routing and other middleware
};
```

If you use a custom container, ensure the following registrations exist (pseudo-config):
```php
return [
    'factories' => [
        Sentry\State\HubInterface::class => Sirix\SentryPsr\Hub\SentryHubFactory::class,
        Sirix\SentryPsr\Middleware\SentryErrorMiddleware::class => Sirix\SentryPsr\Middleware\SentryErrorMiddlewareFactory::class,
        Sirix\SentryPsr\Helper\SentryHelper::class => Sirix\SentryPsr\Helper\SentryHelperFactory::class,

        // optional logger wiring (any of these will be auto-detected by the factory):
        // Psr\Log\LoggerInterface::class => YourLoggerFactory::class,
        // Monolog\Logger::class => YourMonologFactory::class,
        // 'logger' => YourLegacyLoggerFactory::class,
    ],
    'sentry' => [
        'dsn' => 'https://<key>@sentry.io/<project>',
    ],
];
```

## Console integration (optional)

If you use Symfony Console (directly or via Laminas CLI), you can capture console command errors and enrich the Sentry scope.

Available pieces:

- `Sirix\SentryPsr\Listener\SentryCommandListener` subscribes to console events:
  - adds a breadcrumb when a command starts
  - on errors, captures the exception and logs useful context (command, args, options, exit code)
- `Sirix\SentryPsr\ConsoleEventDispatcher\ConsoleEventDispatcherFactory` produces a `Symfony\Component\EventDispatcher\EventDispatcher`, which you can share with `symfony/console` or `laminas/laminas-cli`.

Manual wiring example (Mezzio/Laminas with Laminas CLI):

```php
use Sirix\SentryPsr\Listener\SentryCommandListener;
use Sirix\SentryPsr\Listener\SentryCommandListenerFactory;
use Sirix\SentryPsr\ConsoleEventDispatcher\ConsoleEventDispatcherFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;

return [
    'factories' => [
        Sentry\State\HubInterface::class => Sirix\SentryPsr\Hub\SentryHubFactory::class,
        Sirix\SentryPsr\Listener\SentryCommandListener::class => SentryCommandListenerFactory::class,
        EventDispatcher::class => ConsoleEventDispatcherFactory::class,
        // For Laminas CLI specifically (it expects this alias):
        'Laminas\Cli\SymfonyEventDispatcher' => ConsoleEventDispatcherFactory::class,
    ],
];
```

Then register the listener with your dispatcher during app bootstrap, for example:

```php
$dispatcher = $container->get(Symfony\Component\EventDispatcher\EventDispatcher::class);
$listener   = $container->get(Sirix\SentryPsr\Listener\SentryCommandListener::class);
$dispatcher->addSubscriber($listener);
```

If you build the dispatcher in your `ConsoleApplicationFactory`, just call `addSubscriber()` there.

## Logging

`SentryErrorMiddlewareFactory` will attempt to inject a PSR-3 logger if available. It looks up, in order:

1. `Psr\Log\LoggerInterface`
2. `Monolog\Logger`
3. service name `'logger'`

If none are present, logging is skipped and only Sentry capture occurs.

## Helper: SentryHelper

`Sirix\\SentryPsr\\Helper\\SentryHelper` is a tiny static helper around the current Sentry hub. It provides convenient methods to capture exceptions/messages and enrich the Sentry scope with breadcrumbs, user, tags, and context.

How it is wired:
- When you use this package's `ConfigProvider`, a factory (`SentryHelperFactory`) is registered. Fetching `SentryHelper::class` from the container ensures the helper is initialized with the container's `HubInterface`.
- If you don't use the `ConfigProvider`, you can initialize manually via either of the following:
  - `SentryHelper::initFromContainer($container);`
  - `SentryHelper::setHub($container->get(\Sentry\State\HubInterface::class));`

Common usage:

```php
use Sirix\SentryPsr\Helper\SentryHelper;

// Capture an exception with extra context
try {
    // ... your code ...
} catch (\Throwable $e) {
    SentryHelper::captureException($e, ['foo' => 'bar']);
}

// Capture a message at different levels
SentryHelper::captureMessage('Something happened', 'warning', ['userId' => 123]);
SentryHelper::captureMessage('Debug info', 'debug');

// Add a breadcrumb
SentryHelper::addBreadcrumb('User clicked checkout', 'ui', 'info', ['step' => 'shipping']);

// Enrich scope
SentryHelper::setUser(['id' => '123', 'email' => 'alice@example.com']);
SentryHelper::setTag('feature', 'checkout');
SentryHelper::setContext('request', ['ip' => '203.0.113.10']);
```

Notes:
- Levels for `captureMessage`: `debug`, `info`, `warning`, `error` (default), `fatal`.
- Levels for `addBreadcrumb`: `debug`, `info` (default), `warning`, `error`, `fatal`.

## Examples

- Capture unhandled HTTP exceptions: place `SentryErrorMiddleware` early in your middleware pipeline.
- Enrich context: set `environment`, `release`, `traces_sample_rate` in your `sentry` config.
- Console command tracking: register `SentryCommandListener` with your `EventDispatcher`.

## Development

Useful Composer scripts:

- `composer test` – run PHPUnit
- `composer cs-check` / `composer cs-fix` – code style
- `composer phpstan` – static analysis
- `composer rector` – automated refactoring (dry-run by default)
- `composer check` – run all checks

## License

MIT © Sirix
