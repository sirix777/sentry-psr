# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


## [1.2.0] - 2025-11-23
### Added
- Conditional service aliases when Symfony EventDispatcher is available:
  - `Laminas\Cli\SymfonyEventDispatcher` alias for Laminas CLI integration.
  - `Psr\EventDispatcher\EventDispatcherInterface` alias for PSR-compliant dispatching.
- Factory registration for `Symfony\Component\EventDispatcher\EventDispatcher` via `ConsoleEventDispatcherFactory` when present.

### Changed
- Expanded PHP version support to include ~8.5 in `composer.json`.
- Refined `ConfigProvider` to conditionally wire console listener and event dispatcher only if related packages are installed.
- README updated with clearer requirements, DI wiring, and optional console/logging notes.
- Tooling/dev-deps refresh (PHPUnit, Monolog, static analysis and CS scripts).

### Removed
- Dropped support for PHP 8.1.

### Notes
- No breaking changes; public APIs remain compatible.

## [1.1.0] - 2025-10-30
### Added
- Helper `Sirix\SentryPsr\Helper\SentryHelper` to simplify adding breadcrumbs, contexts, and capturing exceptions/events.
- Factory `Sirix\SentryPsr\Helper\SentryHelperFactory` to wire the helper via a PSR-11 container.
- PHPUnit tests for the helper and its factory.

### Changed
- `ConfigProvider` updated to register `SentryHelper` and its factory.
- README expanded with helper usage and notes about console integration.

## [1.0.0] - 2025-10-30
### Added
- PSR-15 HTTP middleware `SentryErrorMiddleware` to capture uncaught exceptions and forward them to Sentry while allowing normal response handling when no exceptions occur.
- Optional PSR-3 logger integration within the middleware to log exceptions alongside Sentry reports, including request path context.
- Console error listener and dispatcher wiring to capture exceptions occurring in CLI commands.
- `ConfigProvider` for easy framework integration (e.g., Mezzio/Laminas), exposing default service wiring.
- `SentryHubFactory` and related factories to create and configure Sentry hub instances via a PSR-11 container.
- Comprehensive PHPUnit test suite for middleware, listeners, factories, and configuration.

### Compatibility
- PHP 8.1–8.4 supported.
- Compatible with PSR-11 (container), PSR-7 (HTTP messages), PSR-15 (HTTP server middleware), and PSR-3 (logger).
- Uses `sentry/sentry` ^4.0.

### Tooling
- Configured coding standards (PHP-CS-Fixer), static analysis (PHPStan), rector, and dependency analysis scripts via Composer scripts in `composer.json`.

