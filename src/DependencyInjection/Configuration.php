<?php

namespace Wexample\SymfonyFile\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const ROOT_PROJECT = 'project';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_file');

        $treeBuilder->getRootNode()
            ->children()
            ->arrayNode('roots')
            ->useAttributeAsKey('name')
            ->scalarPrototype()
            ->end()
            ->defaultValue([
                self::ROOT_PROJECT => '%kernel.project_dir%',
            ])
            ->end()
            ->end();

        return $treeBuilder;
    }
}
