<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Listener;

use Psr\Log\LoggerInterface;
use Sentry\Breadcrumb;
use Sentry\State\HubInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SentryCommandListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly HubInterface $sentryHub,
        private readonly ?LoggerInterface $logger = null
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::ERROR => ['onConsoleError', -128],
            ConsoleEvents::COMMAND => ['onConsoleCommand', -128],
        ];
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();

        $this->sentryHub->configureScope(function($scope) use ($command, $event): void {
            $scope->setTag('command', $command?->getName() ?? 'unknown');
            $scope->setTag('type', 'console');
            $scope->setContext('command', [
                'name' => $command?->getName(),
                'arguments' => $event->getInput()->getArguments(),
                'options' => $event->getInput()->getOptions(),
            ]);
        });

        $breadcrumb = new Breadcrumb(
            'info',
            'console',
            'Console command started',
        );

        $this->sentryHub->addBreadcrumb($breadcrumb);

        $this->logger?->info('Console command started', [
            'command' => $command?->getName(),
        ]);
    }

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $exception = $event->getError();
        $command = $event->getCommand();

        $this->sentryHub->configureScope(function($scope) use ($command, $event): void {
            $scope->setTag('command', $command?->getName() ?? 'unknown');
            $scope->setTag('exit_code', (string) $event->getExitCode());
            $scope->setContext('command', [
                'name' => $command?->getName(),
                'arguments' => $event->getInput()->getArguments(),
                'options' => $event->getInput()->getOptions(),
            ]);
        });

        $exceptionId = $this->sentryHub->captureException($exception);

        $this->logger?->error('Command failed with exception', [
            'command' => $command?->getName(),
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'sentry_id' => $exceptionId,
        ]);
    }
}
