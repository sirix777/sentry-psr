# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


## [1.0.0] - 30/10/2025
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

