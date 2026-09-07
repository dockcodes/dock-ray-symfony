<?php

declare(strict_types=1);

namespace Dock\Ray\Symfony\DependencyInjection;

use Dock\Ray\AuthData;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ray');

        $treeBuilder->getRootNode()
            ->children()
            ->scalarNode('token')->defaultNull()->end()
            ->scalarNode('private_key')->defaultNull()->end()
            ->scalarNode('url')->defaultValue(AuthData::DEFAULT_URL)->end()
            ->scalarNode('environment')->defaultValue('%kernel.environment%')->end()
            ->scalarNode('release')->defaultNull()->end()
            ->floatNode('sample_rate')->defaultValue(1.0)->min(0)->max(1)->end()
            ->floatNode('traces_sample_rate')->defaultValue(0.0)->min(0)->max(1)->end()
            ->booleanNode('send_default_pii')->defaultFalse()->end()
            ->booleanNode('send_default_user')->defaultTrue()->end()
            ->arrayNode('ignore_exceptions')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
            ->arrayNode('ignore_transactions')
            ->info('Ścieżki pomijane przy pomiarze ruchu; dopasowanie po prefiksie.')
            ->scalarPrototype()->end()
            ->defaultValue(['/_wdt', '/_profiler'])
            ->end()
            ->arrayNode('options')
            ->info('Opcje przekazywane wprost do SDK.')
            ->variablePrototype()->end()
            ->defaultValue([])
            ->end()
            ->end();

        return $treeBuilder;
    }
}
