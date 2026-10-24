<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class RayExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration, $configs);

        foreach (['token', 'private_key', 'url', 'environment', 'release', 'sample_rate', 'traces_sample_rate', 'send_default_pii', 'send_default_user', 'ignore_exceptions', 'ignore_transactions', 'options', 'inventory', 'security'] as $key) {
            $container->setParameter('ray.'.$key, $config[$key]);
        }

        foreach (['inventory', 'security'] as $section) {
            foreach ($config[$section] as $key => $value) {
                $container->setParameter('ray.'.$section.'.'.$key, $value);
            }
        }

        (new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config')))->load('services.php');
    }

    public function getAlias(): string
    {
        return 'ray';
    }
}
