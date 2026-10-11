<?php

declare(strict_types=1);

use Dock\Ray\Inventory\InventoryReporter;
use Dock\Ray\State\HubInterface;
use Dock\Ray\Symfony\Command\InventoryCommand;
use Dock\Ray\Symfony\EventListener\ConsoleErrorListener;
use Dock\Ray\Symfony\EventListener\ExceptionListener;
use Dock\Ray\Symfony\EventListener\TracingListener;
use Dock\Ray\Symfony\EventListener\UserListener;
use Dock\Ray\Symfony\HubFactory;
use Dock\Ray\Symfony\Inventory\InventoryFactory;
use Dock\Ray\Symfony\Scheduler\InventoryHandler;
use Dock\Ray\Symfony\Scheduler\InventorySchedule;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

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

    $services->set(InventoryReporter::class)
        ->factory([InventoryFactory::class, 'create'])
        ->args([
            [
                'token' => '%ray.token%', 'private_key' => '%ray.private_key%', 'url' => '%ray.url%',
                'enabled' => '%ray.inventory.enabled%', 'site_url' => '%ray.inventory.site_url%',
                'exclude_packages' => '%ray.inventory.exclude_packages%',
            ],
            '%kernel.project_dir%', '%ray.inventory.state_directory%', service('logger')->nullOnInvalid(),
        ]);
    $services->set(InventoryCommand::class)
        ->args([service(InventoryReporter::class)])
        ->tag('console.command', ['command' => 'ray:inventory']);

    if (interface_exists(ScheduleProviderInterface::class) && interface_exists(MessageBusInterface::class)) {
        $services->set(InventorySchedule::class)
            ->args(['%ray.inventory.enabled%'])
            ->tag('scheduler.schedule_provider', ['name' => 'dockray_inventory']);
        $services->set(InventoryHandler::class)
            ->args([service(InventoryReporter::class), service('logger')->nullOnInvalid()])
            ->tag('messenger.message_handler');
    }

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
