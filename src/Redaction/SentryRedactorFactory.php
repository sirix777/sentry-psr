<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Redaction;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\RedactorInterface;
use Sirix\Redaction\RedactorOptions;
use Sirix\Redaction\Rule\Factory\SharedRuleFactory;
use Sirix\SentryPsr\Config\SentryPsrConfig;

final readonly class SentryRedactorFactory
{
    private const DEFAULT_SENSITIVE_KEY_PATTERN = '/password|passwd|secret|token|api[_-]?key|authorization|cookie/i';
    private const DEFAULT_REPLACEMENT           = '[Filtered]';

    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): RedactorInterface
    {
        $containerResolver = ContainerResolver::forFactory($container, self::class);
        $configReader      = ConfigReader::fromContainer($containerResolver);
        SentryPsrConfig::assertConfigured($configReader);

        return self::create(
            sensitiveKeyPattern: $configReader->requiredString('sentry_psr.redaction.sensitive_key_pattern'),
            replacement: $configReader->requiredString('sentry_psr.redaction.replacement'),
            maxDepth: $configReader->requiredInt('sentry_psr.redaction.max_depth'),
            maxItemsPerContainer: $configReader->requiredInt('sentry_psr.redaction.max_items_per_container'),
            maxTotalNodes: $configReader->requiredInt('sentry_psr.redaction.max_total_nodes'),
        );
    }

    public static function create(
        string $sensitiveKeyPattern = self::DEFAULT_SENSITIVE_KEY_PATTERN,
        string $replacement = self::DEFAULT_REPLACEMENT,
        int $maxDepth = 8,
        int $maxItemsPerContainer = 100,
        int $maxTotalNodes = 5000,
    ): RedactorInterface {
        return new Redactor(
            customRules: [
                SharedRuleFactory::regexKey(
                    $sensitiveKeyPattern,
                    SharedRuleFactory::fixedValue($replacement),
                ),
            ],
            useDefaultRules: false,
            redactorOptions: new RedactorOptions(
                maxDepth: $maxDepth,
                maxItemsPerContainer: $maxItemsPerContainer,
                maxTotalNodes: $maxTotalNodes,
                overflowPlaceholder: $replacement,
            ),
        );
    }
}
