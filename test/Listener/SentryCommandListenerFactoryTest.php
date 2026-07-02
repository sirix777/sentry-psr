<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Listener;

use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use Sentry\State\HubInterface;
use Sirix\Redaction\RedactorInterface;
use Sirix\SentryPsr\ExceptionFilter\ExceptionFilterInterface;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Listener\SentryCommandListener;
use Sirix\SentryPsr\Listener\SentryCommandListenerFactory;
use Sirix\SentryPsr\Test\Config\SentryPsrConfigFixture;
use Sirix\SentryPsr\Test\Container\InMemoryContainer;

/**
 * @internal
 */
#[CoversClass(SentryCommandListenerFactory::class)]
final class SentryCommandListenerFactoryTest extends TestCase
{
    private HubInterface $hubMock;
    private SentryLifecycle $lifecycle;
    private SentryCommandListenerFactory $factory;

    public function setUp(): void
    {
        parent::setUp();

        $this->hubMock   = $this->createMock(HubInterface::class);
        $this->lifecycle = new SentryLifecycle($this->hubMock);
        $this->factory   = new SentryCommandListenerFactory();
    }

    public function testCreatesListenerWithoutLogger(): void
    {
        $listener = $this->factory->__invoke(new InMemoryContainer([
            HubInterface::class    => $this->hubMock,
            SentryLifecycle::class => $this->lifecycle,
            'config'               => SentryPsrConfigFixture::config(),
        ]));

        $this->assertInstanceOf(SentryCommandListener::class, $listener);
        $this->assertNull((new ReflectionProperty($listener, 'logger'))->getValue($listener));
    }

    public function testCreatesListenerWithPsrLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $listener = $this->factory->__invoke(new InMemoryContainer([
            HubInterface::class    => $this->hubMock,
            SentryLifecycle::class => $this->lifecycle,
            LoggerInterface::class => $logger,
            'config'               => SentryPsrConfigFixture::config(),
        ]));

        $this->assertInstanceOf(SentryCommandListener::class, $listener);
        $this->assertSame($logger, (new ReflectionProperty($listener, 'logger'))->getValue($listener));
    }

    public function testCreatesListenerWithMonologLogger(): void
    {
        $logger = $this->createMock(Logger::class);

        $listener = $this->factory->__invoke(new InMemoryContainer([
            HubInterface::class    => $this->hubMock,
            SentryLifecycle::class => $this->lifecycle,
            'Monolog\Logger'       => $logger,
            'config'               => SentryPsrConfigFixture::config(),
        ]));

        $this->assertInstanceOf(SentryCommandListener::class, $listener);
        $this->assertSame($logger, (new ReflectionProperty($listener, 'logger'))->getValue($listener));
    }

    public function testCreatesListenerWithLoggerAlias(): void
    {
        $logger = $this->createMock(Logger::class);

        $listener = $this->factory->__invoke(new InMemoryContainer([
            HubInterface::class    => $this->hubMock,
            SentryLifecycle::class => $this->lifecycle,
            'logger'               => $logger,
            'config'               => SentryPsrConfigFixture::config(),
        ]));

        $this->assertInstanceOf(SentryCommandListener::class, $listener);
        $this->assertSame($logger, (new ReflectionProperty($listener, 'logger'))->getValue($listener));
    }

    public function testFactoryAppliesConsoleConfigurationFlags(): void
    {
        $listener = $this->factory->__invoke(new InMemoryContainer([
            HubInterface::class    => $this->hubMock,
            SentryLifecycle::class => $this->lifecycle,
            'config'               => SentryPsrConfigFixture::config([
                'isolate_console_scope'      => false,
                'flush_on_console_terminate' => false,
                'capture_console_input'      => false,
                'log_console_command_start'  => false,
            ]),
        ]));

        $this->assertFalse((new ReflectionProperty($listener, 'isolateScope'))->getValue($listener));
        $this->assertFalse((new ReflectionProperty($listener, 'flushOnTerminate'))->getValue($listener));
        $this->assertFalse((new ReflectionProperty($listener, 'captureConsoleInput'))->getValue($listener));
        $this->assertFalse((new ReflectionProperty($listener, 'logConsoleCommandStart'))->getValue($listener));
    }

    public function testFactoryUsesConfiguredDefaultRedactor(): void
    {
        $listener = $this->factory->__invoke(new InMemoryContainer([
            HubInterface::class    => $this->hubMock,
            SentryLifecycle::class => $this->lifecycle,
            'config'               => SentryPsrConfigFixture::config([
                'redaction' => [
                    'replacement'           => '[Redacted]',
                    'sensitive_key_pattern' => '/secret/i',
                ],
            ]),
        ]));

        $redactor = (new ReflectionProperty($listener, 'redactor'))->getValue($listener);
        $this->assertInstanceOf(RedactorInterface::class, $redactor);
        $this->assertSame([
            'clientSecret' => '[Redacted]',
            'password'     => 'visible-with-custom-pattern',
        ], $redactor->redact([
            'clientSecret' => 'secret-value',
            'password'     => 'visible-with-custom-pattern',
        ]));
    }

    public function testFactoryIgnoresGenericRedactorService(): void
    {
        $genericRedactor = $this->createMock(RedactorInterface::class);

        $listener = $this->factory->__invoke(new InMemoryContainer([
            HubInterface::class      => $this->hubMock,
            SentryLifecycle::class   => $this->lifecycle,
            RedactorInterface::class => $genericRedactor,
            'config'                 => SentryPsrConfigFixture::config(),
        ]));

        $redactor = (new ReflectionProperty($listener, 'redactor'))->getValue($listener);
        $this->assertNotSame($genericRedactor, $redactor);
        $this->assertInstanceOf(RedactorInterface::class, $redactor);
        $this->assertSame([
            'api-key'       => '[Filtered]',
            'refresh-token' => '[Filtered]',
            'mode'          => 'smoke',
        ], $redactor->redact([
            'api-key'       => 'fake-api-key-should-not-appear-in-sentry',
            'refresh-token' => 'fake-refresh-token-should-not-appear-in-sentry',
            'mode'          => 'smoke',
        ]));
    }

    public function testFactoryUsesCustomExceptionFilterService(): void
    {
        $exceptionFilter = $this->createMock(ExceptionFilterInterface::class);

        $listener = $this->factory->__invoke(new InMemoryContainer([
            HubInterface::class             => $this->hubMock,
            SentryLifecycle::class          => $this->lifecycle,
            ExceptionFilterInterface::class => $exceptionFilter,
            'config'                        => SentryPsrConfigFixture::config(),
        ]));

        $this->assertSame($exceptionFilter, (new ReflectionProperty($listener, 'exceptionFilter'))->getValue($listener));
    }
}
