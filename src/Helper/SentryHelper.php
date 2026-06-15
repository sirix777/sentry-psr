<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Helper;

use Psr\Container\ContainerInterface;
use Sentry\Breadcrumb;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Throwable;

final class SentryHelper
{
    private static ?HubInterface $hub = null;

    public static function setHub(HubInterface $hub): void
    {
        self::$hub = $hub;
    }

    public static function getHub(): HubInterface
    {
        return self::$hub ?? SentrySdk::getCurrentHub();
    }

    /**
     * Capture an exception and send it to Sentry.
     *
     * @param array<string, mixed> $context Additional contextual data to attach to the Sentry scope
     */
    public static function captureException(Throwable $exception, array $context = []): void
    {
        $hub = self::getHub();

        $hub->withScope(function($scope) use ($hub, $exception, $context): void {
            if ([] !== $context) {
                $scope->setContext('additional_context', $context);
            }

            $scope->setLevel(Severity::error());

            $hub->captureException($exception);
        });
    }

    /**
     * Capture a message and send it to Sentry.
     *
     * @param array<string, mixed> $context Additional contextual data to attach to the Sentry scope
     */
    public static function captureMessage(string $message, string $level = 'error', array $context = []): void
    {
        $hub = self::getHub();

        $severity = match ($level) {
            'debug'   => Severity::debug(),
            'info'    => Severity::info(),
            'warning' => Severity::warning(),
            'fatal'   => Severity::fatal(),
            default   => Severity::error(),
        };

        $hub->withScope(function($scope) use ($hub, $message, $severity, $context): void {
            if ([] !== $context) {
                $scope->setContext('additional_context', $context);
            }

            $scope->setLevel($severity);

            $hub->captureMessage($message);
        });
    }

    /**
     * Initialize Sentry helper from container.
     */
    public static function initFromContainer(ContainerInterface $container): void
    {
        if ($container->has(HubInterface::class)) {
            self::setHub($container->get(HubInterface::class));
        }
    }

    /**
     * Add breadcrumb to Sentry.
     *
     * @param array<string, mixed> $data Additional metadata for the breadcrumb
     */
    public static function addBreadcrumb(string $message, string $category = 'custom', string $level = 'info', array $data = []): void
    {
        $hub = self::getHub();

        $breadcrumbLevel = match ($level) {
            'debug'   => Breadcrumb::LEVEL_DEBUG,
            'warning' => Breadcrumb::LEVEL_WARNING,
            'error'   => Breadcrumb::LEVEL_ERROR,
            'fatal'   => Breadcrumb::LEVEL_FATAL,
            default   => Breadcrumb::LEVEL_INFO,
        };

        $hub->addBreadcrumb(new Breadcrumb(
            level: $breadcrumbLevel,
            type: Breadcrumb::TYPE_DEFAULT,
            category: $category,
            message: $message,
            metadata: $data,
        ));
    }

    /**
     * Set user context for Sentry.
     *
     * @param array<string, mixed> $user User data to attach to the Sentry scope
     */
    public static function setUser(array $user): void
    {
        $hub = self::getHub();

        $hub->configureScope(function($scope) use ($user): void {
            $scope->setUser($user);
        });
    }

    /**
     * Set tag for Sentry.
     */
    public static function setTag(string $key, string $value): void
    {
        $hub = self::getHub();

        $hub->configureScope(function($scope) use ($key, $value): void {
            $scope->setTag($key, $value);
        });
    }

    /**
     * Set context for Sentry.
     *
     * @param array<string, mixed> $context Additional contextual data to attach under the given key
     */
    public static function setContext(string $key, array $context): void
    {
        $hub = self::getHub();

        $hub->configureScope(function($scope) use ($key, $context): void {
            $scope->setContext($key, $context);
        });
    }
}
