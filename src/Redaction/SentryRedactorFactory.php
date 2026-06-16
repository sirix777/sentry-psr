<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Redaction;

use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\RedactorInterface;
use Sirix\Redaction\RedactorOptions;
use Sirix\Redaction\Rule\Factory\SharedRuleFactory;
use Sirix\Redaction\Rule\RedactionRuleInterface;
use Sirix\SentryPsr\Config\SentryPsrConfig;

use function is_array;

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
            useDefaultRules: $configReader->requiredBool('sentry_psr.redaction.use_default_rules'),
            rules: $configReader->requiredMap('sentry_psr.redaction.rules'),
            regexRules: $configReader->requiredList('sentry_psr.redaction.regex_rules'),
        );
    }

    /**
     * @param array<string, array<string, mixed>> $rules
     * @param list<array<string, mixed>>          $regexRules
     */
    public static function create(
        string $sensitiveKeyPattern = self::DEFAULT_SENSITIVE_KEY_PATTERN,
        string $replacement = self::DEFAULT_REPLACEMENT,
        int $maxDepth = 8,
        int $maxItemsPerContainer = 100,
        int $maxTotalNodes = 5000,
        bool $useDefaultRules = false,
        array $rules = [],
        array $regexRules = [],
    ): RedactorInterface {
        $customRules = [
            SharedRuleFactory::regexKey(
                $sensitiveKeyPattern,
                SharedRuleFactory::fixedValue($replacement),
            ),
        ];

        foreach ($rules as $key => $ruleConfig) {
            $customRules[$key] = self::ruleFromConfig($ruleConfig);
        }

        foreach ($regexRules as $regexRule) {
            $ruleConfig = $regexRule['rule'] ?? null;
            if (! is_array($ruleConfig)) {
                continue;
            }

            $customRules[] = SharedRuleFactory::regexKey(
                (string) ($regexRule['pattern'] ?? ''),
                self::ruleFromConfig($ruleConfig),
            );
        }

        return new Redactor(
            customRules: $customRules,
            useDefaultRules: $useDefaultRules,
            redactorOptions: new RedactorOptions(
                replacement: $replacement,
                maxDepth: $maxDepth,
                maxItemsPerContainer: $maxItemsPerContainer,
                maxTotalNodes: $maxTotalNodes,
                overflowPlaceholder: $replacement,
            ),
        );
    }

    /**
     * @param array<string, mixed> $ruleConfig
     */
    private static function ruleFromConfig(array $ruleConfig): RedactionRuleInterface
    {
        return match ($ruleConfig['type'] ?? null) {
            'fixed_value'       => SharedRuleFactory::fixedValue((string) ($ruleConfig['value'] ?? self::DEFAULT_REPLACEMENT)),
            'full_mask'         => SharedRuleFactory::fullMask(),
            'start_end'         => SharedRuleFactory::startEnd((int) ($ruleConfig['start'] ?? 0), (int) ($ruleConfig['end'] ?? 0)),
            'unicode_start_end' => SharedRuleFactory::unicodeStartEnd((int) ($ruleConfig['start'] ?? 0), (int) ($ruleConfig['end'] ?? 0)),
            'email'             => SharedRuleFactory::email(),
            'phone'             => SharedRuleFactory::phone(),
            'name'              => SharedRuleFactory::name(),
            'null'              => SharedRuleFactory::null(),
            'offset'            => SharedRuleFactory::offset((int) ($ruleConfig['offset'] ?? 0)),
            default             => throw new InvalidArgumentException('Unsupported Sentry redaction rule type'),
        };
    }
}
