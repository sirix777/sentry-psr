<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Redaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sirix\Redaction\RedactorInterface;
use Sirix\SentryPsr\Redaction\SentryRedactorFactory;
use Sirix\SentryPsr\Test\Config\SentryPsrConfigFixture;
use Sirix\SentryPsr\Test\Container\InMemoryContainer;

/**
 * @internal
 */
#[CoversClass(SentryRedactorFactory::class)]
final class SentryRedactorFactoryTest extends TestCase
{
    public function testCreatesDefaultConsoleInputRedactor(): void
    {
        $redactor = SentryRedactorFactory::create();

        $this->assertInstanceOf(RedactorInterface::class, $redactor);
        $this->assertSame([
            'username'    => 'admin',
            'password'    => '[Filtered]',
            'nested'      => [
                'api-key' => '[Filtered]',
                'mode'    => 'sync',
            ],
            'accessToken' => '[Filtered]',
        ], $redactor->redact([
            'username'    => 'admin',
            'password'    => 'secret-password',
            'nested'      => [
                'api-key' => 'secret-key',
                'mode'    => 'sync',
            ],
            'accessToken' => 'secret-token',
        ]));
    }

    public function testCreatesRedactorFromSentryPsrConfiguration(): void
    {
        $redactor = (new SentryRedactorFactory())->__invoke(new InMemoryContainer([
            'config' => SentryPsrConfigFixture::config([
                'redaction' => [
                    'replacement'             => '[Redacted]',
                    'sensitive_key_pattern'   => '/secret/i',
                    'max_depth'               => 2,
                    'max_items_per_container' => 10,
                    'max_total_nodes'         => 100,
                ],
            ]),
        ]));

        $this->assertSame([
            'clientSecret' => '[Redacted]',
            'password'     => 'visible-with-custom-pattern',
        ], $redactor->redact([
            'clientSecret' => 'secret-value',
            'password'     => 'visible-with-custom-pattern',
        ]));
    }
}
