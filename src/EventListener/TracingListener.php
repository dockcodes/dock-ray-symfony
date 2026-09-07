<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony\EventListener;

use Dock\Ray\Framework\HttpTransaction;
use Dock\Ray\State\HubInterface;
use Dock\Ray\State\Scope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class TracingListener implements EventSubscriberInterface
{
    private ?HttpTransaction $transaction = null;

    /**
     * @param  string[]  $ignoredPaths
     */
    public function __construct(
        private readonly HubInterface $hub,
        private readonly array $ignoredPaths = [],
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 4096],
            KernelEvents::TERMINATE => ['onTerminate', -4096],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest() || $this->isIgnored($event->getRequest())) {
            return;
        }

        $request = $event->getRequest();

        $this->transaction = HttpTransaction::start(
            $request->getMethod().' '.$request->getPathInfo(),
            $request->getUri(),
            $request->getMethod(),
            $request->server->get('REQUEST_TIME_FLOAT'),
            $this->hub,
        );

        $this->transaction->measureHandling('symfony.handle');
    }

    /**
     * Transakcję zamyka `kernel.terminate`, a nie `kernel.response`: dopiero tam
     * kończy się praca żądania, którą i tak płaci użytkownik.
     */
    public function onTerminate(TerminateEvent $event): void
    {
        if ($this->transaction === null) {
            return;
        }

        $this->rememberRoute($event->getRequest());
        $this->transaction->finish($event->getResponse()->getStatusCode());
        $this->transaction = null;
    }

    private function rememberRoute(Request $request): void
    {
        $route = $request->attributes->get('_route');

        if (! is_string($route) || $route === '') {
            return;
        }

        $this->hub->configureScope(static function (Scope $scope) use ($route): void {
            $scope->setTag('route', $route);
        });
    }

    private function isIgnored(Request $request): bool
    {
        foreach ($this->ignoredPaths as $path) {
            if (str_starts_with($request->getPathInfo(), $path)) {
                return true;
            }
        }

        return false;
    }
}
