<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony\Inventory;

use Dock\Ray\AuthData;
use Dock\Ray\Inventory\InventoryReporter;
use Dock\Ray\Options;
use Psr\Log\LoggerInterface;

final class InventoryFactory
{
    public static function create(array $config, string $projectDirectory, string $stateDirectory, ?LoggerInterface $logger = null): InventoryReporter
    {
        // Explicit configured URL only: never infer the site from an incoming Host header.
        return new InventoryReporter(new Options([
            'token' => $config['token'],
            'private_key' => $config['private_key'],
            'url' => $config['url'] ?? AuthData::DEFAULT_URL,
            'inventory_enabled' => $config['enabled'],
            'inventory_site_url' => $config['site_url'],
            'inventory_exclude_packages' => $config['exclude_packages'],
        ]), $projectDirectory, $stateDirectory, 'php', null, $logger);
    }
}
