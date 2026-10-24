<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony\EventListener;

use Dock\Ray\Symfony\Security\SecurityReporter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The scheduler's console process cannot tell what visitors get from PHP
 * (`display_errors`, `exec_available`, whether code is writable); the security
 * report takes these from a recent page view. Runs on `kernel.terminate`,
 * after the response went out, and stores at most once an hour.
 */
final class SecurityPostureListener implements EventSubscriberInterface
{
    /**
     * @param \Closure(): SecurityReporter $reporter built only when the report is enabled
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly \Closure $reporter,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => ['onTerminate', -8192]];
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if (! $this->enabled) {
            return;
        }

        try {
            if (! $event->isMainRequest() || ! self::isPageView($event->getRequest())) {
                return;
            }

            ($this->reporter)()->captureWebPosture();
        } catch (\Throwable $exception) {
            // Monitoring must not add an error to a response that went fine.
        }
    }

    /** Not AJAX, not a client asking for JSON, not `/api`. */
    private static function isPageView(Request $request): bool
    {
        if ($request->isXmlHttpRequest()) {
            return false;
        }

        $accept = strtolower((string) ($request->getAcceptableContentTypes()[0] ?? ''));

        if (str_contains($accept, '/json') || str_contains($accept, '+json')) {
            return false;
        }

        $path = $request->getPathInfo();

        return $path !== '/api' && ! str_starts_with($path, '/api/');
    }
}
