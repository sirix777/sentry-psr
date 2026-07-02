<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\ExceptionFilter;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\SentryPsr\Config\SentryPsrConfig;

use function array_map;

final readonly class ConfiguredExceptionFilterFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): ExceptionFilterInterface
    {
        $containerResolver = ContainerResolver::forFactory($container, self::class);
        $configReader      = ConfigReader::fromContainer($containerResolver);
        SentryPsrConfig::assertConfigured($configReader);

        return self::fromConfigReader($configReader);
    }

    public static function fromConfigReader(ConfigReader $configReader): ConfiguredExceptionFilter
    {
        return new ConfiguredExceptionFilter(
            enabled: $configReader->bool('sentry_psr.exception_filter.enabled', true),
            ignoredClasses: $configReader->stringList('sentry_psr.exception_filter.ignore_classes', []),
            ignoredHttpStatuses: self::intList($configReader, 'sentry_psr.exception_filter.ignore_http_statuses'),
            ignoredCodes: self::intList($configReader, 'sentry_psr.exception_filter.ignore_codes'),
            ignoredMessagePatterns: $configReader->stringList('sentry_psr.exception_filter.ignore_message_patterns', []),
            inspectPrevious: $configReader->bool('sentry_psr.exception_filter.inspect_previous', true),
        );
    }

    /**
     * @return list<int>
     */
    private static function intList(ConfigReader $configReader, string $path): array
    {
        $values = $configReader->list($path, []);

        return array_map(static fn (mixed $value): int => (int) $value, $values);
    }
}
