<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Tests\Unit\Proxy;

use PHPUnit\Framework\TestCase;
use Sfrpc\Pool\ConnectionPool\ConnectionPool;
use Sfrpc\Pool\Proxy\AbstractGrpcProxy;

class DummyProxy extends AbstractGrpcProxy
{
    public function getPool(): ConnectionPool
    {
        return $this->pool;
    }
}

class AbstractGrpcProxyTest extends TestCase
{
    public function testProxyStoresConnectionPool(): void
    {
        $mockPool = $this->createMock(ConnectionPool::class);
        $proxy = new DummyProxy($mockPool);

        $this->assertSame($mockPool, $proxy->getPool());
    }
}
