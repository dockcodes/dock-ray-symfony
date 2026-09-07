<?php

declare(strict_types=1);

use Dock\Ray\State\HubInterface;
use Dock\Ray\Symfony\EventListener\ConsoleErrorListener;
use Dock\Ray\Symfony\EventListener\ExceptionListener;
use Dock\Ray\Symfony\EventListener\TracingListener;
use Dock\Ray\Symfony\EventListener\UserListener;
use Dock\Ray\Symfony\HubFactory;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(HubInterface::class)
        ->factory([HubFactory::class, 'create'])
        ->args([
            [
                'token' => '%ray.token%',
                'private_key' => '%ray.private_key%',
                'url' => '%ray.url%',
                'environment' => '%ray.environment%',
                'release' => '%ray.release%',
                'sample_rate' => '%ray.sample_rate%',
                'traces_sample_rate' => '%ray.traces_sample_rate%',
                'send_default_pii' => '%ray.send_default_pii%',
                'options' => '%ray.options%',
            ],
            '%kernel.project_dir%',
        ])
        ->public();

    $services->alias('ray.hub', HubInterface::class)->public();

    $services->set(ExceptionListener::class)
        ->args([service(HubInterface::class), '%ray.ignore_exceptions%'])
        ->tag('kernel.event_subscriber');

    $services->set(TracingListener::class)
        ->args([service(HubInterface::class), '%ray.ignore_transactions%'])
        ->tag('kernel.event_subscriber');

    if (class_exists(ConsoleEvents::class)) {
        $services->set(ConsoleErrorListener::class)
            ->args([service(HubInterface::class)])
            ->tag('kernel.event_subscriber');
    }

    if (interface_exists(TokenStorageInterface::class)) {
        $services->set(UserListener::class)
            ->args([service(HubInterface::class), service('security.token_storage')->nullOnInvalid()])
            ->tag('kernel.event_subscriber');
    }
};
