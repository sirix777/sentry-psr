<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Listener;

use Psr\Log\LoggerInterface;
use Sentry\Breadcrumb;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sirix\Redaction\RedactorInterface;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Redaction\SentryRedactorFactory;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SentryCommandListener implements EventSubscriberInterface
{
    private int $activeScopes = 0;

    public function __construct(
        private readonly HubInterface $sentryHub,
        private readonly bool $isolateScope = true,
        private readonly bool $flushOnTerminate = true,
        private readonly bool $captureConsoleInput = true,
        private readonly bool $logConsoleCommandStart = true,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?SentryLifecycle $sentryLifecycle = null,
        private readonly ?RedactorInterface $redactor = null,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::ERROR     => ['onConsoleError', -128],
            ConsoleEvents::COMMAND   => ['onConsoleCommand', 128],
            ConsoleEvents::TERMINATE => ['onConsoleTerminate', -128],
        ];
    }

    public function onConsoleCommand(ConsoleCommandEvent $consoleCommandEvent): void
    {
        if ($this->isolateScope) {
            $this->lifecycle()->pushScope();
            ++$this->activeScopes;
        }

        $this->configureCommandScope($consoleCommandEvent);
        $this->addCommandBreadcrumb($consoleCommandEvent);

        if ($this->logConsoleCommandStart) {
            $this->logger?->info('Console command started', [
                'command' => $consoleCommandEvent->getCommand()?->getName(),
            ]);
        }
    }

    public function onConsoleError(ConsoleErrorEvent $consoleErrorEvent): void
    {
        if ($this->isolateScope && 0 === $this->activeScopes) {
            $this->lifecycle()->withIsolatedScope(function(Scope $scope) use ($consoleErrorEvent): void {
                $this->configureErrorScope($scope, $consoleErrorEvent);
                $this->captureAndLogError($consoleErrorEvent);

                if ($this->flushOnTerminate) {
                    $this->lifecycle()->flush();
                }
            });

            return;
        }

        $this->configureErrorScope(null, $consoleErrorEvent);
        $this->captureAndLogError($consoleErrorEvent);
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $consoleTerminateEvent): void
    {
        if ($this->flushOnTerminate) {
            $this->lifecycle()->flush();
        }

        if ($this->isolateScope && $this->activeScopes > 0) {
            $this->lifecycle()->popScope();
            --$this->activeScopes;
        }
    }

    private function configureCommandScope(ConsoleCommandEvent $consoleCommandEvent): void
    {
        $command = $consoleCommandEvent->getCommand();

        $this->lifecycle()->configureScope(function(Scope $scope) use ($command, $consoleCommandEvent): void {
            $scope->setTag('command', $command?->getName() ?? 'unknown');
            $scope->setTag('type', 'console');
            $scope->setContext('command', $this->commandContext($consoleCommandEvent));
        });
    }

    private function configureErrorScope(?Scope $scope, ConsoleErrorEvent $consoleErrorEvent): void
    {
        $command = $consoleErrorEvent->getCommand();

        $configure = function(Scope $targetScope) use ($command, $consoleErrorEvent): void {
            $targetScope->setTag('command', $command?->getName() ?? 'unknown');
            $targetScope->setTag('exit_code', (string) $consoleErrorEvent->getExitCode());
            $targetScope->setContext('command', $this->commandContext($consoleErrorEvent));
        };

        if ($scope instanceof Scope) {
            $configure($scope);

            return;
        }

        $this->lifecycle()->configureScope($configure);
    }

    private function addCommandBreadcrumb(ConsoleCommandEvent $consoleCommandEvent): void
    {
        $command = $consoleCommandEvent->getCommand();

        $this->sentryHub->addBreadcrumb(new Breadcrumb(
            level: Breadcrumb::LEVEL_INFO,
            type: Breadcrumb::TYPE_DEFAULT,
            category: 'console',
            message: 'Console command started',
            metadata: [
                'command' => $command?->getName(),
            ],
        ));
    }

    private function captureAndLogError(ConsoleErrorEvent $consoleErrorEvent): void
    {
        $throwable   = $consoleErrorEvent->getError();
        $command     = $consoleErrorEvent->getCommand();
        $exceptionId = $this->sentryHub->captureException($throwable);

        $this->logger?->error('Command failed with exception', [
            'command'   => $command?->getName(),
            'exception' => $throwable->getMessage(),
            'trace'     => $throwable->getTraceAsString(),
            'sentry_id' => $exceptionId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function commandContext(ConsoleCommandEvent|ConsoleErrorEvent $event): array
    {
        $command = $event->getCommand();
        $context = [
            'name' => $command?->getName(),
        ];

        if ($this->captureConsoleInput) {
            $context['arguments'] = $this->redactInputValues($event->getInput()->getArguments());
            $context['options']   = $this->redactInputValues($event->getInput()->getOptions());
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function redactInputValues(array $values): mixed
    {
        return $this->redactor()->redact($values);
    }

    private function lifecycle(): SentryLifecycle
    {
        return $this->sentryLifecycle ?? new SentryLifecycle($this->sentryHub, logger: $this->logger);
    }

    private function redactor(): RedactorInterface
    {
        return $this->redactor ?? SentryRedactorFactory::create();
    }
}
