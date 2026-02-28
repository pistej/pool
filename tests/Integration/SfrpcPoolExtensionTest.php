<?php
declare(strict_types=1);

namespace Sfrpc\Pool\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sfrpc\Pool\ConnectionPool\ConnectionPool;
use Sfrpc\Pool\DependencyInjection\SfrpcPoolExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DummyProxy
{
}

class SfrpcPoolExtensionTest extends TestCase
{
    public function testLoadConfigAndRegisterServices(): void
    {
        $container = new ContainerBuilder();
        $extension = new SfrpcPoolExtension();

        // Mock config mimicking config/packages/sfrpc_pool.yaml
        $config = [
            'worker_started_event' => 'App\Event\WorkerStart',
            'pools' => [
                'greeter' => [
                    'host' => '127.0.0.1',
                    'port' => 50051,
                    'min_active' => 3,
                    'proxies' => [
                        DummyProxy::class
                    ]
                ]
            ]
        ];

        $extension->load([$config], $container);

        // Assert the pool was registered
        $poolId = 'sfrpc_pool.pool.greeter';
        $this->assertTrue($container->hasDefinition($poolId), 'Pool definition should exist');

        $poolDef = $container->getDefinition($poolId);
        $this->assertSame(ConnectionPool::class, $poolDef->getClass());

        // Assert the proxy was registered and injects the pool
        $this->assertTrue($container->hasDefinition(DummyProxy::class), 'Proxy definition should exist');

        $proxyDef = $container->getDefinition(DummyProxy::class);
        $args = $proxyDef->getArguments();
        $this->assertCount(1, $args);
        $this->assertSame($poolId, (string) $args[0], 'Proxy should have the pool injected as reference');
    }
}
