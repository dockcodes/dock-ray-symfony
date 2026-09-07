<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony\EventListener;

use Dock\Ray\State\HubInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ExceptionListener implements EventSubscriberInterface
{
    /**
     * @param  string[]  $ignoredExceptions
     */
    public function __construct(
        private readonly HubInterface $hub,
        private readonly array $ignoredExceptions = [],
    ) {}

    public static function getSubscribedEvents(): array
    {
        /*
         | Priorytet ujemny stawia nas za listenerami, które mogą wyjątek
         | podmienić albo obsłużyć — zgłaszamy to, czym błąd naprawdę się
         | skończył.
         */
        return [KernelEvents::EXCEPTION => ['onException', -128]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        foreach ($this->ignoredExceptions as $class) {
            if ($exception instanceof $class) {
                return;
            }
        }

        try {
            $this->hub->captureException($exception);
        } catch (\Throwable) {
            // Awaria monitoringu nie może zastąpić błędu, który obsługujemy.
        }
    }
}
