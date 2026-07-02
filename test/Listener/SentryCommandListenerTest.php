<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Listener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\EventId;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sirix\SentryPsr\ExceptionFilter\ExceptionFilterContext;
use Sirix\SentryPsr\ExceptionFilter\ExceptionFilterInterface;
use Sirix\SentryPsr\Listener\SentryCommandListener;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @internal
 */
#[CoversClass(SentryCommandListener::class)]
final class SentryCommandListenerTest extends TestCase
{
    private HubInterface&MockObject $hubMock;
    private LoggerInterface&MockObject $loggerMock;
    private SentryCommandListener $listener;

    protected function setUp(): void
    {
        $this->hubMock    = $this->createMock(HubInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->hubMock->method('withScope')->willReturnCallback(static function(callable $callback): mixed {
            return $callback(new Scope());
        });

        $this->listener = new SentryCommandListener($this->hubMock, logger: $this->loggerMock);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = SentryCommandListener::getSubscribedEvents();

        $this->assertArrayHasKey(ConsoleEvents::ERROR, $events);
        $this->assertArrayHasKey(ConsoleEvents::COMMAND, $events);
        $this->assertArrayHasKey(ConsoleEvents::TERMINATE, $events);
        $this->assertSame(['onConsoleError', -128], $events[ConsoleEvents::ERROR]);
        $this->assertSame(['onConsoleCommand', 128], $events[ConsoleEvents::COMMAND]);
        $this->assertSame(['onConsoleTerminate', -128], $events[ConsoleEvents::TERMINATE]);
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

        $listener = new SentryCommandListener($this->hubMock, isolateScope: false, logger: $this->loggerMock);

        $listener->onConsoleError($event);
    }

    public function testOnConsoleErrorSkipsIgnoredExceptionWithoutLoggingOrFlushing(): void
    {
        $command   = new Command('auth:refresh');
        $input     = new ArrayInput([]);
        $output    = new NullOutput();
        $exception = new RuntimeException('Unauthorized', 401);
        $event     = new ConsoleErrorEvent($input, $output, $exception, $command);
        $filter    = $this->createMock(ExceptionFilterInterface::class);

        $filter->expects($this->once())
            ->method('shouldCapture')
            ->with(
                $exception,
                $this->callback(static fn (ExceptionFilterContext $context): bool => ExceptionFilterContext::SOURCE_CONSOLE === $context->source
                    && 'auth:refresh' === $context->consoleCommand
                    && 401 === $context->consoleExitCode)
            )
            ->willReturn(false)
        ;

        $this->hubMock->expects($this->never())->method('captureException');
        $this->hubMock->expects($this->never())->method('getClient');
        $this->loggerMock->expects($this->never())->method('error');

        (new SentryCommandListener(
            $this->hubMock,
            logger: $this->loggerMock,
            exceptionFilter: $filter,
        ))->onConsoleError($event);
    }

    public function testCanDisableConsoleCommandStartedInfoLog(): void
    {
        $command = new Command('quiet:cmd');
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
        $this->loggerMock->expects($this->never())->method('info');

        (new SentryCommandListener(
            $this->hubMock,
            isolateScope: false,
            logConsoleCommandStart: false,
            logger: $this->loggerMock,
        ))->onConsoleCommand($event);
    }

    public function testWorksWithoutLogger(): void
    {
        $listener = new SentryCommandListener($this->hubMock);

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

    public function testOnConsoleTerminateFlushesAndPopsScope(): void
    {
        $command = new Command('test:cmd');
        $input   = new ArrayInput([]);
        $output  = new NullOutput();

        $this->hubMock->expects($this->once())->method('pushScope')->willReturn(new Scope());
        $this->hubMock->expects($this->once())->method('popScope')->willReturn(true);
        $this->hubMock->expects($this->once())->method('getClient')->willReturn(null);

        $this->listener->onConsoleCommand(new ConsoleCommandEvent($command, $input, $output));
        $this->listener->onConsoleTerminate(new ConsoleTerminateEvent($command, $input, $output, 0));
    }

    public function testConsoleInputIsSanitizedRecursively(): void
    {
        $command = new Command('secure:cmd');
        $input   = $this->createMock(InputInterface::class);
        $event   = new ConsoleCommandEvent($command, $input, new NullOutput());

        $input->method('getArguments')->willReturn([
            'username' => 'admin',
            'password' => 'secret-password',
            'nested'   => [
                'api_key'       => 'secret-key',
                'api-key'       => 'secret-key-with-dash',
                'refresh_token' => 'refresh-token',
                'mode'          => 'sync',
            ],
        ]);
        $input->method('getOptions')->willReturn([
            'api-key'     => 'secret-key-option',
            'accessToken' => 'secret-token',
            'verbose'     => true,
        ]);

        $this->hubMock->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function(callable $callback): bool {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->exactly(2))->method('setTag');
                $scope->expects($this->once())
                    ->method('setContext')
                    ->with('command', $this->callback(static function(array $context): bool {
                        return 'secure:cmd' === $context['name']
                            && 'admin' === $context['arguments']['username']
                            && '[Filtered]' === $context['arguments']['password']
                            && '[Filtered]' === $context['arguments']['nested']['api_key']
                            && '[Filtered]' === $context['arguments']['nested']['api-key']
                            && '[Filtered]' === $context['arguments']['nested']['refresh_token']
                            && 'sync' === $context['arguments']['nested']['mode']
                            && '[Filtered]' === $context['options']['api-key']
                            && '[Filtered]' === $context['options']['accessToken']
                            && true === $context['options']['verbose'];
                    }))
                ;

                $callback($scope);

                return true;
            }))
        ;
        $this->hubMock->expects($this->once())->method('addBreadcrumb');

        (new SentryCommandListener($this->hubMock, isolateScope: false))->onConsoleCommand($event);
    }

    public function testConsoleInputCaptureCanBeDisabled(): void
    {
        $command = new Command('secure:cmd');
        $input   = $this->createMock(InputInterface::class);
        $event   = new ConsoleCommandEvent($command, $input, new NullOutput());

        $input->expects($this->never())->method('getArguments');
        $input->expects($this->never())->method('getOptions');

        $this->hubMock->expects($this->once())
            ->method('configureScope')
            ->with($this->callback(function(callable $callback): bool {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->exactly(2))->method('setTag');
                $scope->expects($this->once())
                    ->method('setContext')
                    ->with('command', $this->callback(static function(array $context): bool {
                        return 'secure:cmd' === $context['name']
                            && ! isset($context['arguments'])
                            && ! isset($context['options']);
                    }))
                ;

                $callback($scope);

                return true;
            }))
        ;
        $this->hubMock->expects($this->once())->method('addBreadcrumb');

        (new SentryCommandListener(
            $this->hubMock,
            isolateScope: false,
            captureConsoleInput: false,
        ))->onConsoleCommand($event);
    }

    public function testConsoleErrorWithoutActiveCommandUsesIsolatedScopeAndFlushes(): void
    {
        $command   = new Command('fail:cmd');
        $input     = new ArrayInput([]);
        $output    = new NullOutput();
        $exception = new RuntimeException('Failure');
        $event     = new ConsoleErrorEvent($input, $output, $exception, $command);
        $client    = $this->createMock(ClientInterface::class);
        $eventId   = new EventId('b27d9f5b3c234d1ab0e11f76bb6af2e7');
        $hub       = $this->createMock(HubInterface::class);

        $client->expects($this->once())
            ->method('flush')
            ->with(2)
            ->willReturn(new Result(ResultStatus::success()))
        ;

        $hub->expects($this->once())
            ->method('withScope')
            ->willReturnCallback(function(callable $callback): mixed {
                $scope = $this->createMock(Scope::class);
                $scope->expects($this->exactly(2))->method('setTag');
                $scope->expects($this->once())->method('setContext');

                return $callback($scope);
            })
        ;
        $hub->expects($this->once())->method('captureException')->with($exception)->willReturn($eventId);
        $hub->expects($this->once())->method('getClient')->willReturn($client);

        (new SentryCommandListener($hub))->onConsoleError($event);
    }

    public function testConsoleTerminateDoesNotPopWithoutActiveScope(): void
    {
        $command = new Command('test:cmd');
        $input   = new ArrayInput([]);
        $output  = new NullOutput();

        $this->hubMock->expects($this->once())->method('getClient')->willReturn(null);
        $this->hubMock->expects($this->never())->method('popScope');

        $this->listener->onConsoleTerminate(new ConsoleTerminateEvent($command, $input, $output, 0));
    }

    public function testConsoleTerminateCanSkipFlush(): void
    {
        $command = new Command('test:cmd');
        $input   = new ArrayInput([]);
        $output  = new NullOutput();

        $this->hubMock->expects($this->never())->method('getClient');
        $this->hubMock->expects($this->never())->method('popScope');

        (new SentryCommandListener(
            $this->hubMock,
            isolateScope: false,
            flushOnTerminate: false,
        ))->onConsoleTerminate(new ConsoleTerminateEvent($command, $input, $output, 0));
    }
}
