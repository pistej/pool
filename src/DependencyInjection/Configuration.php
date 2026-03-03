<?php

declare(strict_types=1);

namespace Sfrpc\Pool\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sfrpc_pool');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->scalarNode('worker_started_event')
            ->defaultNull()
            ->info('The FQCN of the worker started event to hook into (e.g. SwooleBundle\Server\Event\WorkerStartedEvent). If left null, pools must be initialized manually or by a custom subscriber.')
            ->end()
            ->arrayNode('pools')
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->children()
            ->scalarNode('host')->isRequired()->cannotBeEmpty()->end()
            ->integerNode('port')->isRequired()->end()
            ->booleanNode('ssl')->defaultFalse()->end()
            ->integerNode('min_active')->defaultValue(1)->end()
            ->integerNode('max_active')->defaultValue(10)->end()
            ->floatNode('max_wait_time')->defaultValue(5.0)->end()
            ->floatNode('max_idle_time')->defaultValue(30.0)->end()
            ->floatNode('idle_check_interval')->defaultValue(10.0)->end()
            ->booleanNode('debug_logs')->defaultFalse()->end()
            ->arrayNode('swoole_settings')
            ->scalarPrototype()->end()
            ->end()
            ->arrayNode('proxies')
            ->scalarPrototype()->end()
            ->info('A list of FQCNs of generated Proxy classes that should use this pool.')
            ->end()
            ->end()
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
