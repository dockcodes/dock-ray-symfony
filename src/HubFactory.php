<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony;

use Dock\Ray\ClientBuilder;
use Dock\Ray\State\Hub;
use Dock\Ray\State\HubInterface;
use Dock\Ray\RaySdk;

/**
 * Buduje hub z konfiguracji bundle'a i ustawia go jako bieżący, żeby funkcje
 * globalne SDK (`captureException()` i reszta) działały w kodzie aplikacji.
 */
final class HubFactory
{
    public static function create(array $config, string $projectDir): HubInterface
    {
        $options = array_merge($config['options'] ?? [], [
            'token' => $config['token'],
            'private_key' => $config['private_key'],
            'url' => $config['url'],
            'environment' => $config['environment'],
            'release' => $config['release'],
            'sample_rate' => (float) $config['sample_rate'],
            'traces_sample_rate' => (float) $config['traces_sample_rate'],
            'send_default_pii' => (bool) $config['send_default_pii'],
            'prefixes' => [$projectDir],
            'in_app_exclude' => [$projectDir.'/vendor', $projectDir.'/var'],
        ]);

        $builder = ClientBuilder::create(array_filter($options, static fn ($value): bool => $value !== null));
        $builder->setSdkIdentifier('ray.php.symfony');
        $builder->setSdkVersion(RayBundle::VERSION);

        $hub = new Hub($builder->getClient());

        RaySdk::setCurrentHub($hub);

        return $hub;
    }
}
