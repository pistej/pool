<?php

declare(strict_types=1);

namespace Sfrpc\Pool\DependencyInjection;

use Sfrpc\Pool\ConnectionPool\ConnectionPool;
use Sfrpc\Pool\ConnectionPool\GrpcConnector;
use Sfrpc\Pool\ConnectionPool\PoolConfig;
use Sfrpc\Pool\EventSubscriber\PoolLifecycleSubscriber;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class SfrpcPoolExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        /** @var array{worker_started_event?: string, pools: array<string, array{min_active: int, max_active: int, max_wait_time: float, max_idle_time: float, idle_check_interval: float, host: string, port: int, ssl: bool, swoole_settings?: array<string, mixed>, proxies?: string[]}>} $config */
        $config = $this->processConfiguration($configuration, $configs);

        $poolServices = [];
        $defaultPoolId = null;
        foreach ($config['pools'] as $name => $poolConfig) {
            $poolConfigDef = new Definition(PoolConfig::class, [
                $poolConfig['min_active'],
                $poolConfig['max_active'],
                $poolConfig['max_wait_time'],
                $poolConfig['max_idle_time'],
                $poolConfig['idle_check_interval']
            ]);
            $poolConfigId = sprintf('sfrpc_pool.config.%s', $name);
            $container->setDefinition($poolConfigId, $poolConfigDef);

            $connectorDef = new Definition(GrpcConnector::class, [
                $poolConfig['host'],
                $poolConfig['port'],
                $poolConfig['ssl'],
                $poolConfig['swoole_settings'] ?? []
            ]);
            $connectorId = sprintf('sfrpc_pool.connector.%s', $name);
            $container->setDefinition($connectorId, $connectorDef);

            $poolDef = new Definition(ConnectionPool::class, [
                new Reference($poolConfigId),
                new Reference($connectorId),
                new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE)
            ]);
            $poolDef->addTag('sfrpc_pool.pool');
            $poolDef->setAutowired(true);
            $poolDef->setPublic(true);
            $poolId = sprintf('sfrpc_pool.pool.%s', $name);
            $container->setDefinition($poolId, $poolDef);

            $poolServices[] = new Reference($poolId);

            if ($defaultPoolId === null || $name === 'default') {
                $defaultPoolId = $poolId;
            }

            if (isset($poolConfig['proxies']) && is_array($poolConfig['proxies'])) {
                foreach ($poolConfig['proxies'] as $proxyClass) {
                    $proxyDef = new Definition($proxyClass, [
                        new Reference($poolId)
                    ]);
                    $proxyDef->setAutowired(true);
                    $proxyDef->setAutoconfigured(true);
                    $proxyDef->setPublic(true);
                    $proxyDef->addTag('sfrpc_pool.proxy');
                    $container->setDefinition($proxyClass, $proxyDef);
                }
            }
        }

        if ($defaultPoolId !== null) {
            $container->setAlias(ConnectionPool::class, $defaultPoolId)->setPublic(true);
        }

        if (!empty($config['worker_started_event'])) {
            $subscriberDef = new Definition(PoolLifecycleSubscriber::class, [
                $poolServices
            ]);
            $subscriberDef->setAutowired(true);
            $subscriberDef->setPublic(true);
            $subscriberDef->addTag('kernel.event_listener', [
                'event' => $config['worker_started_event'],
                'method' => 'onWorkerStarted'
            ]);
            $container->setDefinition(PoolLifecycleSubscriber::class, $subscriberDef);
        }
    }
}
