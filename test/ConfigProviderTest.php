<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Sentry\State\HubInterface;
use Sirix\SentryPsr\ConfigProvider;
use Sirix\SentryPsr\ConsoleEventDispatcher\ConsoleEventDispatcherFactory;
use Sirix\SentryPsr\Hub\SentryHubFactory;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Lifecycle\SentryLifecycleFactory;
use Sirix\SentryPsr\Listener\SentryCommandListener;
use Sirix\SentryPsr\Listener\SentryCommandListenerFactory;
use Sirix\SentryPsr\Middleware\SentryErrorMiddleware;
use Sirix\SentryPsr\Middleware\SentryErrorMiddlewareFactory;
use Sirix\SentryPsr\Reporter\SentryReporter;
use Sirix\SentryPsr\Reporter\SentryReporterFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function class_exists;

/**
 * @internal
 */
#[CoversClass(ConfigProvider::class)]
final class ConfigProviderTest extends TestCase
{
    public function testConfigProviderReturnsDependencies(): void
    {
        $provider = new ConfigProvider();
        $config   = $provider();

        $this->assertArrayHasKey('dependencies', $config);
        $dependencies = $config['dependencies'];

        $this->assertArrayHasKey('factories', $dependencies);
        $factories = $dependencies['factories'];

        $this->assertArrayHasKey(HubInterface::class, $factories);
        $this->assertSame(SentryHubFactory::class, $factories[HubInterface::class]);

        $this->assertArrayHasKey(SentryLifecycle::class, $factories);
        $this->assertSame(SentryLifecycleFactory::class, $factories[SentryLifecycle::class]);

        $this->assertArrayHasKey(SentryReporter::class, $factories);
        $this->assertSame(SentryReporterFactory::class, $factories[SentryReporter::class]);

        $this->assertArrayHasKey(SentryErrorMiddleware::class, $factories);
        $this->assertSame(SentryErrorMiddlewareFactory::class, $factories[SentryErrorMiddleware::class]);

        if (class_exists(Command::class)) {
            $this->assertArrayHasKey(SentryCommandListener::class, $factories);
            $this->assertSame(SentryCommandListenerFactory::class, $factories[SentryCommandListener::class]);

            $this->assertArrayHasKey(EventDispatcher::class, $factories);
            $this->assertSame(ConsoleEventDispatcherFactory::class, $factories[EventDispatcher::class]);
        }

        $this->assertArrayHasKey('aliases', $dependencies);
        $aliases = $dependencies['aliases'];

        if (class_exists(Command::class)) {
            $this->assertArrayHasKey('Laminas\Cli\SymfonyEventDispatcher', $aliases);
            $this->assertSame(EventDispatcher::class, $aliases['Laminas\Cli\SymfonyEventDispatcher']);

            $this->assertArrayHasKey(EventDispatcherInterface::class, $aliases);
            $this->assertSame(EventDispatcher::class, $aliases[EventDispatcherInterface::class]);
        }
    }

    public function testConfigProviderDoesNotShipRuntimeConfigDefaults(): void
    {
        $config = (new ConfigProvider())();

        $this->assertArrayNotHasKey('sentry_psr', $config);
    }

    public function testConfigProviderWithoutCommandClass(): void
    {
        $provider = new class extends ConfigProvider {
            protected function getFactories(): array
            {
                $factories = [
                    HubInterface::class          => SentryHubFactory::class,
                    SentryErrorMiddleware::class => SentryErrorMiddlewareFactory::class,
                ];

                if (class_exists('NonExistentCommand')) {
                    $factories['dummy'] = 'dummy';
                }

                return $factories;
            }

            protected function getAliases(): array
            {
                if (class_exists('NonExistentCommand')) {
                    return [
                        'Laminas\Cli\SymfonyEventDispatcher' => EventDispatcher::class,
                    ];
                }

                return [];
            }
        };

        $config = $provider();

        $factories = $config['dependencies']['factories'];
        $aliases   = $config['dependencies']['aliases'];

        $this->assertArrayNotHasKey('dummy', $factories);
        $this->assertEmpty($aliases);
    }
}
