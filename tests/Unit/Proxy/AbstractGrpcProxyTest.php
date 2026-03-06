<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Tests\Unit\Proxy;

use PHPUnit\Framework\TestCase;
use Sfrpc\Pool\ConnectionPool\ConnectionPool;
use Sfrpc\Pool\Proxy\AbstractGrpcProxy;

class AbstractGrpcProxyTest extends TestCase
{
    public function testExecuteInContextWithoutCoroutine(): void
    {
        $pool = $this->createMock(ConnectionPool::class);
        $proxy = new class ($pool) extends AbstractGrpcProxy {
            public function executeContextTest(callable $test): mixed
            {
                return $this->executeInContext($test);
            }
        };

        $result = $proxy->executeContextTest(function () {
            // Should be running in a coroutine now
            return \Swoole\Coroutine::getCid() !== -1;
        });

        $this->assertTrue($result, 'Should have executed inside a coroutine context');
    }

    public function testExecuteInContextWithException(): void
    {
        $pool = $this->createMock(ConnectionPool::class);
        $proxy = new class ($pool) extends AbstractGrpcProxy {
            public function executeContextTest(callable $test): mixed
            {
                return $this->executeInContext($test);
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test exception from coroutine');

        $proxy->executeContextTest(function () {
            throw new \RuntimeException('Test exception from coroutine');
        });
    }

    public function testExecuteInContextAlreadyInCoroutine(): void
    {
        $pool = $this->createMock(ConnectionPool::class);
        $proxy = new class ($pool) extends AbstractGrpcProxy {
            public function executeContextTest(callable $test): mixed
            {
                return $this->executeInContext($test);
            }
        };

        \Swoole\Coroutine\run(function () use ($proxy) {
            $cid = \Swoole\Coroutine::getCid();

            $result = $proxy->executeContextTest(function () use ($cid) {
                return \Swoole\Coroutine::getCid() === $cid;
            });

            $this->assertTrue($result, 'Should execute in the SAME coroutine if already in one');
        });
    }
}
