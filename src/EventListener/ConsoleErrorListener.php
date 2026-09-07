<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony\EventListener;

use Dock\Ray\State\HubInterface;
use Dock\Ray\State\Scope;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ConsoleErrorListener implements EventSubscriberInterface
{
    public function __construct(private readonly HubInterface $hub) {}

    public static function getSubscribedEvents(): array
    {
        return [ConsoleEvents::ERROR => ['onError', -128]];
    }

    public function onError(ConsoleErrorEvent $event): void
    {
        $command = $event->getCommand();

        try {
            $this->hub->withScope(function (Scope $scope) use ($event, $command): void {
                $scope->setTag('command', $command?->getName() ?? 'unknown');
                $this->hub->captureException($event->getError());
            });
        } catch (\Throwable) {
            // Jak wyżej: monitoring nie przykrywa błędu polecenia.
        }
    }
}
