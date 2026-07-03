<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sfrpc\Pool\ConnectionPool\ConnectionPool;
use Sfrpc\Pool\DependencyInjection\SfrpcPoolExtension;
use Sfrpc\Pool\Tests\Fixtures\DummyClientInterface;
use Sfrpc\Pool\Tests\Fixtures\DummyProxy;
use Sfrpc\Pool\Tests\Fixtures\DummyUnrelatedInterface;
use Sfrpc\Pool\Tests\Fixtures\UnrelatedAppClientInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SfrpcPoolExtensionTest extends TestCase
{
    public function testLoadConfigAndRegisterServices(): void
    {
        $container = new ContainerBuilder();
        $extension = new SfrpcPoolExtension();

        $config = [
            'worker_started_event' => 'App\Event\WorkerStart',
            'worker_stop_event' => 'App\Event\WorkerStop',
            'worker_exit_event' => 'App\Event\WorkerExit',
            'worker_error_event' => 'App\Event\WorkerError',
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

        // 1. Assert Pool
        $poolId = 'sfrpc_pool.pool.greeter';
        $this->assertTrue($container->hasDefinition($poolId));
        $poolDef = $container->getDefinition($poolId);
        $this->assertTrue($poolDef->isPublic());
        $this->assertTrue($poolDef->isAutowired());
        $this->assertTrue($poolDef->hasTag('sfrpc_pool.pool'));

        // 2. Assert Alias for ConnectionPool::class
        $this->assertTrue($container->hasAlias(ConnectionPool::class));
        $this->assertSame($poolId, (string) $container->getAlias(ConnectionPool::class));

        // 3. Assert Proxy
        $this->assertTrue($container->hasDefinition(DummyProxy::class));
        $proxyDef = $container->getDefinition(DummyProxy::class);
        $this->assertTrue($proxyDef->isPublic());
        $this->assertTrue($proxyDef->isAutowired());
        $this->assertTrue($proxyDef->hasTag('sfrpc_pool.proxy'));

        // 4. Assert lifecycle subscriber listeners
        $subscriberDef = $container->getDefinition(\Sfrpc\Pool\EventSubscriber\PoolLifecycleSubscriber::class);
        /** @var array<int, array{event: string, method: string}> $listeners */
        $listeners = $subscriberDef->getTag('kernel.event_listener');
        $this->assertCount(4, $listeners);

        /** @var array<string, string> $listenerMap */
        $listenerMap = [];
        foreach ($listeners as $listener) {
            $listenerMap[$listener['event']] = $listener['method'];
        }

        $this->assertSame('onWorkerStarted', $listenerMap['App\Event\WorkerStart']);
        $this->assertSame('onWorkerStopped', $listenerMap['App\Event\WorkerStop']);
        $this->assertSame('onWorkerExited', $listenerMap['App\Event\WorkerExit']);
        $this->assertSame('onWorkerErrored', $listenerMap['App\Event\WorkerError']);
    }

    public function testCompilerPassAliasesInterfaces(): void
    {
        $container = new ContainerBuilder();
        $extension = new SfrpcPoolExtension();

        $config = [
            'pools' => [
                'default' => [
                    'host' => '127.0.0.1',
                    'port' => 80,
                    'proxies' => [DummyProxy::class]
                ]
            ]
        ];

        $extension->load([$config], $container);

        // Now manually run the pass
        $pass = new \Sfrpc\Pool\DependencyInjection\Compiler\ProxyInterfaceAliasPass();
        $pass->process($container);

        // Assert that DummyClientInterface is now an alias for the DummyProxy class definition
        $this->assertTrue($container->hasAlias(DummyClientInterface::class));
        $this->assertSame(DummyProxy::class, (string) $container->getAlias(DummyClientInterface::class));
        $this->assertTrue($container->getAlias(DummyClientInterface::class)->isPublic());
    }

    public function testCompilerPassDoesNotAliasNonClientInterfaces(): void
    {
        $container = new ContainerBuilder();
        $extension = new SfrpcPoolExtension();

        $config = [
            'pools' => [
                'default' => [
                    'host' => '127.0.0.1',
                    'port' => 80,
                    'proxies' => [DummyProxy::class]
                ]
            ]
        ];

        $extension->load([$config], $container);

        $pass = new \Sfrpc\Pool\DependencyInjection\Compiler\ProxyInterfaceAliasPass();
        $pass->process($container);

        // DummyProxy also implements DummyUnrelatedInterface, which does not follow the
        // generated "...ClientInterface" naming convention. It must not be hijacked by
        // aliasing it to the proxy service (H2).
        $this->assertFalse($container->hasAlias(DummyUnrelatedInterface::class));

        // DummyProxy also implements UnrelatedAppClientInterface, which *does* happen to
        // match the "*ClientInterface" naming convention (e.g. a host application's own,
        // unrelated "PaymentClientInterface"-style interface) but is not a generated
        // sfrpc client contract (it doesn't extend SfrpcClientInterface). A naming-based
        // check alone would incorrectly alias it; only the SfrpcClientInterface marker
        // check should decide this (H2).
        $this->assertFalse($container->hasAlias(UnrelatedAppClientInterface::class));
    }

    public function testRejectsMinActiveGreaterThanMaxActive(): void
    {
        $container = new ContainerBuilder();
        $extension = new SfrpcPoolExtension();

        $config = [
            'pools' => [
                'default' => [
                    'host' => '127.0.0.1',
                    'port' => 80,
                    'min_active' => 5,
                    'max_active' => 2,
                ]
            ]
        ];

        // min_active > max_active would otherwise let init() push more connections
        // than the pool channel's capacity, silently leaking the excess ones (M5/C3).
        $this->expectException(InvalidConfigurationException::class);

        $extension->load([$config], $container);
    }
}
