<?php

declare(strict_types=1);

namespace Ossm\MagicLinkBundle\DependencyInjection;

use Ossm\MagicLinkBundle\Manager\MagicLinkManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('magic_link');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
            ->integerNode('token_ttl')
            ->info('Default lifetime of an issued link, in seconds. Overridable per issue().')
            ->defaultValue(MagicLinkManager::DEFAULT_TTL)
            ->min(1)
            ->end()
            ->end();

        return $treeBuilder;
    }
}
