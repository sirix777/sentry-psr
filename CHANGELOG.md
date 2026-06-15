# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - Unreleased
### Added
- Added `sentry_psr` package-level configuration for scope isolation, global hub behavior, flush behavior and context enrichment.
- Added `SentryLifecycle` as the central API for isolated scopes and safe client flush.
- Added `SentryReporter` as the recommended injectable helper/reporter service.
- Added HTTP request context enrichment with privacy-safe header filtering.
- Added `UPGRADE-2.0.md` with migration guidance for breaking changes.

### Changed
- HTTP requests are isolated in a Sentry scope by default for long-running workers.
- Console commands now push a command-local scope on `COMMAND` and pop it on `TERMINATE`.
- `SentryHubFactory` creates explicit `Hub` instances via `ClientBuilder` and only sets the global current hub when configured.
- Factories now use `sirix/container-resolver` for strict PSR-11 service and config resolution.
- `psr/log` is a core dependency for logger type safety, while concrete logger implementations and Symfony console/event-dispatcher integrations remain optional.
- GitHub Actions and local quality gates include dependency analysis with targeted optional-integration ignores.

### Removed
- Removed legacy static `SentryHelper` and `SentryHelperFactory`; use `SentryReporter` through DI instead.

### Fixed
- Console command breadcrumbs and lifecycle no longer leak across sequential commands.

## [1.2.1] - 2026-06-15
### Fixed
- Fixed Symfony Console command breadcrumbs so the Sentry breadcrumb now uses the correct `console` category and `Console command started` message.
- Added the console command name to breadcrumb metadata.

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

