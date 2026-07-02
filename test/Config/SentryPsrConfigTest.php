<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;
use Sirix\ContainerResolver\Exception\MissingConfigValueException;
use Sirix\SentryPsr\Config\SentryPsrConfig;
use Sirix\SentryPsr\Test\Container\InMemoryContainer;

/**
 * @internal
 */
#[CoversClass(SentryPsrConfig::class)]
final class SentryPsrConfigTest extends TestCase
{
    public function testAllowsCompleteSentryPsrSection(): void
    {
        SentryPsrConfig::assertConfigured($this->configReader(SentryPsrConfigFixture::config()));

        $this->assertTrue(true);
    }

    public function testAllowsMissingExceptionFilterForBackwardCompatibility(): void
    {
        $sentryPsr = SentryPsrConfigFixture::sentryPsr();
        unset($sentryPsr['exception_filter']);

        SentryPsrConfig::assertConfigured($this->configReader([
            'sentry_psr' => $sentryPsr,
        ]));

        $this->assertTrue(true);
    }

    public function testThrowsWhenSentryPsrConfigIsMissing(): void
    {
        $this->expectException(MissingConfigValueException::class);

        SentryPsrConfig::assertConfigured($this->configReader([]));
    }

    public function testThrowsWhenSentryPsrConfigIsNotArray(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        SentryPsrConfig::assertConfigured($this->configReader([
            'sentry_psr' => 'invalid',
        ]));
    }

    public function testThrowsWhenRequiredSentryPsrKeyIsMissing(): void
    {
        $this->expectException(MissingConfigValueException::class);

        SentryPsrConfig::assertConfigured($this->configReader([
            'sentry_psr' => [],
        ]));
    }

    public function testThrowsWhenIntegerConfigIsNegative(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        SentryPsrConfig::assertConfigured($this->configReader(SentryPsrConfigFixture::config([
            'flush_timeout' => -1,
        ])));
    }

    public function testThrowsWhenSensitiveKeyPatternIsInvalidRegex(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        SentryPsrConfig::assertConfigured($this->configReader(SentryPsrConfigFixture::config([
            'redaction' => [
                'sensitive_key_pattern' => '(',
            ],
        ])));
    }

    public function testThrowsWhenRedactionRuleTypeIsInvalid(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        SentryPsrConfig::assertConfigured($this->configReader(SentryPsrConfigFixture::config([
            'redaction' => [
                'rules' => [
                    'email' => [
                        'type' => 'unknown',
                    ],
                ],
            ],
        ])));
    }

    public function testThrowsWhenRegexRedactionRulePatternIsInvalid(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        SentryPsrConfig::assertConfigured($this->configReader(SentryPsrConfigFixture::config([
            'redaction' => [
                'regex_rules' => [
                    [
                        'pattern' => '(',
                        'rule'    => [
                            'type' => 'email',
                        ],
                    ],
                ],
            ],
        ])));
    }

    public function testThrowsWhenStartEndRedactionRuleIsMissingRequiredInteger(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        SentryPsrConfig::assertConfigured($this->configReader(SentryPsrConfigFixture::config([
            'redaction' => [
                'rules' => [
                    'card_number' => [
                        'type'  => 'start_end',
                        'start' => 6,
                    ],
                ],
            ],
        ])));
    }

    public function testThrowsWhenExceptionFilterHttpStatusIsNotInteger(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        SentryPsrConfig::assertConfigured($this->configReader(SentryPsrConfigFixture::config([
            'exception_filter' => [
                'ignore_http_statuses' => [
                    '401',
                ],
            ],
        ])));
    }

    public function testThrowsWhenExceptionFilterClassDoesNotExist(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        SentryPsrConfig::assertConfigured($this->configReader(SentryPsrConfigFixture::config([
            'exception_filter' => [
                'ignore_classes' => [
                    'App\Exception\UnauthorzedException',
                ],
            ],
        ])));
    }

    public function testThrowsWhenExceptionFilterMessagePatternIsInvalidRegex(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        SentryPsrConfig::assertConfigured($this->configReader(SentryPsrConfigFixture::config([
            'exception_filter' => [
                'ignore_message_patterns' => [
                    '(',
                ],
            ],
        ])));
    }

    public function testThrowsWhenExceptionFilterSectionIsNotMap(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        SentryPsrConfig::assertConfigured($this->configReader(SentryPsrConfigFixture::config([
            'exception_filter' => [
                'invalid',
            ],
        ])));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configReader(array $config): ConfigReader
    {
        return ConfigReader::fromContainer(ContainerResolver::forFactory(new InMemoryContainer([
            'config' => $config,
        ]), self::class));
    }
}
