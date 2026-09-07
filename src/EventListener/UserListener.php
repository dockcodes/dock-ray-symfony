<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony\EventListener;

use Dock\Ray\State\HubInterface;
use Dock\Ray\State\Scope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Dopina zalogowanego użytkownika do zdarzeń.
 *
 * Czekamy na `kernel.controller`, bo dopiero tam firewall zdążył rozpoznać
 * token — na `kernel.request` magazyn jest jeszcze pusty.
 */
final class UserListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly ?TokenStorageInterface $tokenStorage,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => ['onController', 1024]];
    }

    public function onController(ControllerEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $user = $this->tokenStorage?->getToken()?->getUser();

        if (! $user instanceof UserInterface) {
            return;
        }

        $this->hub->configureScope(static function (Scope $scope) use ($user): void {
            $scope->setUser(array_filter([
                'id' => $user->getUserIdentifier(),
                'email' => method_exists($user, 'getEmail') ? $user->getEmail() : null,
                'username' => $user->getUserIdentifier(),
            ], static fn ($value): bool => $value !== null));
        });
    }
}
