<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Listener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sentry\Breadcrumb;
use Sentry\EventId;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sirix\SentryPsr\Listener\SentryCommandListener;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @internal
 */
#[CoversClass(SentryCommandListener::class)]
final class SentryCommandListenerTest extends TestCase
{
    private HubInterface $hubMock;
    private LoggerInterface $loggerMock;
    private SentryCommandListener $listener;

    protected function setUp(): void
    {
        $this->hubMock    = $this->createMock(HubInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->listener   = new SentryCommandListener($this->hubMock, $this->loggerMock);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = SentryCommandListener::getSubscribedEvents();

        $this->assertArrayHasKey(ConsoleEvents::ERROR, $events);
        $this->assertArrayHasKey(ConsoleEvents::COMMAND, $events);
        $this->assertSame(['onConsoleError', -128], $events[ConsoleEvents::ERROR]);
        $this->assertSame(['onConsoleCommand', -128], $events[ConsoleEvents::COMMAND]);
    }

    public function testOnConsoleCommandConfiguresScopeAndLogs(): void
    {
        $command = new Command('test:cmd');
        $input   = new ArrayInput([
            'arg1' => 'val',
        ]);
        $event = new ConsoleCommandEvent($command, $input, new NullOutput());

        $this->hubMock
            ->expects(self::once())
            ->method('configureScope')
            ->with(self::callback(function($callback): bool {
                $scope = $this->createMock(Scope::class);
                $scope->expects(self::atLeastOnce())->method('setTag');
                $scope->expects(self::once())->method('setContext');
                $callback($scope);

                return true;
            }))
        ;

        $this->hubMock
            ->expects(self::once())
            ->method('addBreadcrumb')
            ->with(self::callback(static function(Breadcrumb $breadcrumb): bool {
                return Breadcrumb::LEVEL_INFO === $breadcrumb->getLevel()
                    && Breadcrumb::TYPE_DEFAULT === $breadcrumb->getType()
                    && 'console' === $breadcrumb->getCategory()
                    && 'Console command started' === $breadcrumb->getMessage()
                    && [
                        'command' => 'test:cmd',
                    ] === $breadcrumb->getMetadata();
            }))
        ;

        $this->loggerMock
            ->expects(self::once())
            ->method('info')
            ->with(
                'Console command started',
                self::callback(fn ($context) => 'test:cmd' === $context['command'])
            )
        ;

        $this->listener->onConsoleCommand($event);
    }

    public function testOnConsoleErrorCapturesExceptionAndLogs(): void
    {
        $command   = new Command('fail:cmd');
        $input     = new ArrayInput([]);
        $output    = new NullOutput();
        $exception = new RuntimeException('Failure');
        $event     = new ConsoleErrorEvent($input, $output, $exception, $command);

        $eventId = new EventId('b27d9f5b3c234d1ab0e11f76bb6af2e7');

        $this->hubMock
            ->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function($callback): bool {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->atLeastOnce())->method('setTag');
                $scope->expects($this->once())->method('setContext');
                $callback($scope);

                return true;
            }))
        ;

        $this->hubMock
            ->expects($this->once())
            ->method('captureException')
            ->with($exception)
            ->willReturn($eventId)
        ;

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with(
                'Command failed with exception',
                $this->callback(function(array $context) use ($eventId): bool {
                    return 'fail:cmd' === $context['command']
                        && 'Failure' === $context['exception']
                        && (string) $context['sentry_id'] === (string) $eventId;
                })
            )
        ;

        $this->listener->onConsoleError($event);
    }

    public function testWorksWithoutLogger(): void
    {
        $listener = new SentryCommandListener($this->hubMock, null);

        $command = new Command('no:log');
        $input   = new ArrayInput([]);
        $event   = new ConsoleCommandEvent($command, $input, new NullOutput());

        $this->hubMock
            ->expects($this->once())
            ->method('configureScope')
        ;

        $this->hubMock
            ->expects($this->once())
            ->method('addBreadcrumb')
        ;

        $listener->onConsoleCommand($event);

        $this->assertTrue(true, 'No exceptions thrown without logger');
    }
}
