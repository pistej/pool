<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Tests\Unit\ConnectionPool;

use PHPUnit\Framework\TestCase;
use Sfrpc\Pool\ConnectionPool\ConnectionPool;
use Sfrpc\Pool\ConnectionPool\ConnectionWrapper;
use Sfrpc\Pool\ConnectionPool\ConnectorInterface;
use Sfrpc\Pool\ConnectionPool\PoolConfig;

class ConnectionPoolTest extends TestCase
{
    public function testPoolBorrowAndReturn(): void
    {
        // Run test in a Swoole coroutine context since the pool uses Swoole Channels
        \Swoole\Coroutine\run(function () {
            $config = new PoolConfig(minActive: 1, maxActive: 2, maxWaitTime: 0.1, idleCheckInterval: 0);

            $dummyConnection = new \stdClass();
            $connector = new class ($dummyConnection) implements ConnectorInterface {
                public function __construct(private object $conn)
                {
                }
                public function connect(): object
                {
                    return $this->conn;
                }
                public function disconnect(object $c): void
                {
                }
                public function isConnected(object $c): bool
                {
                    return true;
                }
                public function reset(object $c): void
                {
                }
            };

            $pool = new ConnectionPool($config, $connector);
            $pool->init();

            $conn1 = $pool->borrow();
            $this->assertSame($dummyConnection, $conn1);

            // Pool should have 1 connection borrowed. We can borrow again up to maxActive=2.
            $conn2 = $pool->borrow();
            $this->assertSame($dummyConnection, $conn2);

            // Third borrow should fail due to timeout
            $caught = false;
            try {
                $pool->borrow();
            } catch (\RuntimeException $e) {
                $caught = true;
                $this->assertStringContainsString('Pool timeout', $e->getMessage());
            }
            $this->assertTrue($caught, 'Expected RuntimeException was not thrown');

            // Test Returning
            $pool->return($conn1);
            $pool->return($conn2);
            $this->assertSame($dummyConnection, $pool->borrow()); // Re-usage works

            $pool->close();
        });
    }

    public function testPoolRecoversDeadConnection(): void
    {
        \Swoole\Coroutine\run(function () {
            $config = new PoolConfig(minActive: 1, maxActive: 1, maxWaitTime: 0.1, idleCheckInterval: 0);
            $dummyConnection = new \stdClass();

            $connector = new class ($dummyConnection) implements ConnectorInterface {
                public int $connects = 0;
                public int $disconnects = 0;
                public bool $simulateDead = false;

                public function __construct(private object $conn)
                {
                }
                public function connect(): object
                {
                    $this->connects++;
                    return $this->conn;
                }
                public function disconnect(object $c): void
                {
                    $this->disconnects++;
                }
                public function isConnected(object $c): bool
                {
                    return !$this->simulateDead;
                }
                public function reset(object $c): void
                {
                }
            };

            $pool = new ConnectionPool($config, $connector);
            $pool->init(); // Connects = 1

            // Borrow successfully
            $conn = $pool->borrow();
            $pool->return($conn);

            // Now, simulate the connection dying while idle in the pool
            $connector->simulateDead = true;

            // When we borrow again, it should detect it's dead, disconnect it, and create a new one recursively
            // But wait, our dummy connector returns simulateDead = true always now, so if we try to recursive borrow
            // it will fail infinite loop!
            // Let's make it only dead ONCE.
            $connector->simulateDead = false; // Reset first
            $pool->borrow(); // Just to get it out
            $pool->return($conn);

            // Now make it dead, but when it reconnects, make it alive again.
            $connector = new class ($dummyConnection) implements ConnectorInterface {
                public int $checks = 0;
                public function __construct(private object $conn)
                {
                }
                public function connect(): object
                {
                    return clone $this->conn;
                }
                public function disconnect(object $c): void
                {
                }
                public function isConnected(object $c): bool
                {
                    $this->checks++;
                    // Fail the first check (detecting dead), pass the second (new connection)
                    return $this->checks > 1;
                }
                public function reset(object $c): void
                {
                }
            };

            $pool2 = new ConnectionPool($config, $connector);
            $pool2->init();

            $newConn = $pool2->borrow();
            $this->assertNotSame($dummyConnection, $newConn); // It's a newly cloned one!

            $pool2->close();
        });
    }

    public function testBorrowThrowsAfterMaxAttemptsInsteadOfRecursingForever(): void
    {
        \Swoole\Coroutine\run(function () {
            // maxActive=2 => bounded retry loop should give up after maxActive+1 = 3 attempts.
            $config = new PoolConfig(minActive: 0, maxActive: 2, maxWaitTime: 0.1, idleCheckInterval: 0);

            // The connector keeps handing back connections that are reported as dead for the
            // first 10 attempts, only "recovering" afterwards. A properly bounded borrow() must
            // give up (RuntimeException) long before that - it must never keep retrying/recursing
            // until the server happens to come back (that's the C1 unbounded-recursion bug).
            $connector = new class implements ConnectorInterface {
                public int $connectCalls = 0;
                public int $isConnectedCalls = 0;

                public function connect(): object
                {
                    $this->connectCalls++;
                    return new \stdClass();
                }
                public function disconnect(object $c): void
                {
                }
                public function isConnected(object $c): bool
                {
                    $this->isConnectedCalls++;
                    return $this->isConnectedCalls > 10;
                }
                public function reset(object $c): void
                {
                }
            };

            $pool = new ConnectionPool($config, $connector);

            $caught = false;
            try {
                $pool->borrow();
            } catch (\RuntimeException $e) {
                $caught = true;
            }

            $this->assertTrue(
                $caught,
                'Expected borrow() to give up with a RuntimeException instead of retrying until the '
                . 'connection eventually recovers'
            );
            // It must have bailed out after a bounded number of attempts (maxActive + 1 = 3),
            // not kept retrying until isConnected() finally returns true (call #11).
            $this->assertLessThanOrEqual(3, $connector->isConnectedCalls);

            $pool->close();
        });
    }

    public function testCreateConnectionDisconnectsAndDoesNotLeakCounterWhenChannelPushFails(): void
    {
        \Swoole\Coroutine\run(function () {
            // Channel capacity == maxActive == 1.
            $config = new PoolConfig(minActive: 0, maxActive: 1, maxWaitTime: 0.1, idleCheckInterval: 0);

            $connector = new class implements ConnectorInterface {
                public int $connects = 0;
                public int $disconnects = 0;

                public function connect(): object
                {
                    $this->connects++;
                    return new \stdClass();
                }
                public function disconnect(object $c): void
                {
                    $this->disconnects++;
                }
                public function isConnected(object $c): bool
                {
                    return true;
                }
                public function reset(object $c): void
                {
                }
            };

            $pool = new ConnectionPool($config, $connector);

            $refClass = new \ReflectionClass(ConnectionPool::class);

            // Fill the channel to capacity directly, so the next createConnection()
            // call is guaranteed to fail its push (channel full - C3/H1 scenario).
            $poolProp = $refClass->getProperty('pool');
            $poolProp->setAccessible(true);
            /** @var \Swoole\Coroutine\Channel $channel */
            $channel = $poolProp->getValue($pool);
            $channel->push(new ConnectionWrapper(new \stdClass(), microtime(true)));

            $createConnection = $refClass->getMethod('createConnection');
            $createConnection->setAccessible(true);
            $createConnection->invoke($pool);

            $countProp = $refClass->getProperty('connectionCount');
            $countProp->setAccessible(true);

            $this->assertSame(1, $connector->connects, 'connect() should have been called once');
            $this->assertSame(
                1,
                $connector->disconnects,
                'The freshly created connection must be disconnected when it cannot be pushed back (C3)'
            );
            $this->assertSame(
                0,
                $countProp->getValue($pool),
                'connectionCount must not be inflated when the channel push fails (C3)'
            );

            $pool->close();
        });
    }

    public function testIdleConnectionReaping(): void
    {
        \Swoole\Coroutine\run(function () {
            // Idle check every 0.1s. Max idle time is 0.2s. Min active is 1. Max active is 2.
            $config = new PoolConfig(
                minActive: 1,
                maxActive: 2,
                maxWaitTime: 0.1,
                maxIdleTime: 0.2,
                idleCheckInterval: 0
            );
            $dummyConnection = new \stdClass();

            $connector = new class ($dummyConnection) implements ConnectorInterface {
                public int $disconnects = 0;
                public function __construct(private object $conn)
                {
                }
                public function connect(): object
                {
                    return clone $this->conn;
                }
                public function disconnect(object $c): void
                {
                    $this->disconnects++;
                }
                public function isConnected(object $c): bool
                {
                    return true;
                }
                public function reset(object $c): void
                {
                }
            };

            $pool = new ConnectionPool($config, $connector);
            $pool->init(); // 1 connection created

            // Borrow and create a second connection
            $conn1 = $pool->borrow();
            $conn2 = $pool->borrow(); // Creates the second one

            // Return them quickly
            $pool->return($conn1);
            $pool->return($conn2);

            // We disable Timer ticks during tests (`idleCheckInterval: 0`) to prevent PHPUnit hangs.
            // We manually invoke the private `checkIdleConnections` using Reflection.
            \Swoole\Coroutine\System::sleep(0.3);

            $refClass = new \ReflectionClass(ConnectionPool::class);
            $checkMethod = $refClass->getMethod('checkIdleConnections');
            $checkMethod->setAccessible(true);
            $checkMethod->invoke($pool);

            // The method should have checked and reaped exactly ONE connection (because minActive=1)
            $this->assertSame(1, $connector->disconnects);

            $pool->close();
        });
    }

    public function testCheckIdleConnectionsRemovesConnectionWhenPushBackFails(): void
    {
        \Swoole\Coroutine\run(function () {
            $config = new PoolConfig(
                minActive: 0,
                maxActive: 2,
                maxWaitTime: 0.1,
                maxIdleTime: 100.0,
                idleCheckInterval: 0
            );

            $connector = new class implements ConnectorInterface {
                public int $disconnects = 0;
                public function connect(): object
                {
                    return new \stdClass();
                }
                public function disconnect(object $c): void
                {
                    $this->disconnects++;
                }
                public function isConnected(object $c): bool
                {
                    return true;
                }
                public function reset(object $c): void
                {
                }
            };

            $pool = new ConnectionPool($config, $connector);
            $refClass = new \ReflectionClass(ConnectionPool::class);

            // Swap in a channel whose push() we can force to fail, to deterministically
            // simulate a full channel at push-back time (H3), without relying on timing
            // races between coroutines.
            $channel = new class (2) extends \Swoole\Coroutine\Channel {
                public bool $forceFail = false;
                public function push(mixed $data, mixed $timeout = -1): bool
                {
                    if ($this->forceFail) {
                        return false;
                    }
                    return parent::push($data, $timeout);
                }
            };
            $poolProp = $refClass->getProperty('pool');
            $poolProp->setAccessible(true);
            $poolProp->setValue($pool, $channel);

            // A single, freshly-used (non-idle) connection sitting in the pool.
            $channel->push(new ConnectionWrapper(new \stdClass(), microtime(true)));
            $countProp = $refClass->getProperty('connectionCount');
            $countProp->setAccessible(true);
            $countProp->setValue($pool, 1);

            // The connection isn't idle, so checkIdleConnections() will try to push it
            // back into the pool. Force that push to fail.
            $channel->forceFail = true;

            $checkMethod = $refClass->getMethod('checkIdleConnections');
            $checkMethod->setAccessible(true);
            $checkMethod->invoke($pool);

            // The connection must be disconnected and the counter decremented instead of
            // being silently dropped (physical connection + counter leak, H3).
            $this->assertSame(1, $connector->disconnects);
            $this->assertSame(0, $countProp->getValue($pool));

            $channel->forceFail = false;
            $pool->close();
        });
    }

    public function testCloseIsIdempotentAndResetsTimer(): void
    {
        \Swoole\Coroutine\run(function () {
            // idleCheckInterval > 0 so init() actually starts a Timer we can inspect.
            // minActive: 1 so init() also creates a connection for close() to drain.
            $config = new PoolConfig(minActive: 1, maxActive: 1, maxWaitTime: 0.1, idleCheckInterval: 60);
            $dummyConnection = new \stdClass();

            $connector = new class ($dummyConnection) implements ConnectorInterface {
                public int $disconnects = 0;
                public function __construct(private object $conn)
                {
                }
                public function connect(): object
                {
                    return $this->conn;
                }
                public function disconnect(object $c): void
                {
                    $this->disconnects++;
                }
                public function isConnected(object $c): bool
                {
                    return true;
                }
                public function reset(object $c): void
                {
                }
            };

            $pool = new ConnectionPool($config, $connector);
            $pool->init();

            $refClass = new \ReflectionClass(ConnectionPool::class);
            $timerProp = $refClass->getProperty('checkTimerId');
            $timerProp->setAccessible(true);
            $this->assertGreaterThan(0, $timerProp->getValue($pool), 'init() should have started the idle-check timer');

            $pool->close();
            $this->assertSame(
                1,
                $connector->disconnects,
                'close() should drain and disconnect the min-active connection created by init()'
            );
            $this->assertSame(
                0,
                $timerProp->getValue($pool),
                'checkTimerId must be reset to 0 after close() so a repeated close() cannot '
                . 're-clear a stale timer id (H4)'
            );

            // Calling close() again must be a safe no-op: no double-disconnects, no errors.
            $pool->close();
            $this->assertSame(1, $connector->disconnects, 'A second close() call must not disconnect again');
        });
    }

    public function testReturnToClosedPool(): void
    {
        \Swoole\Coroutine\run(function () {
            $config = new PoolConfig(maxActive: 1, maxWaitTime: 0.1, idleCheckInterval: 0);
            $dummyConnection = new \stdClass();
            $connector = new class ($dummyConnection) implements ConnectorInterface {
                public int $disconnects = 0;
                public function __construct(private object $conn)
                {
                }
                public function connect(): object
                {
                    return $this->conn;
                }
                public function disconnect(object $c): void
                {
                    $this->disconnects++;
                }
                public function isConnected(object $c): bool
                {
                    return true;
                }
                public function reset(object $c): void
                {
                }
            };

            $pool = new ConnectionPool($config, $connector);
            $pool->init();
            $conn = $pool->borrow();

            $pool->close(); // DisconnectionCount should be 0 because conn is borrowed

            $pool->return($conn); // Should call disconnect manually
            $this->assertSame(1, $connector->disconnects);
        });
    }

    public function testCreateConnectionFailure(): void
    {
        \Swoole\Coroutine\run(function () {
            $config = new PoolConfig(minActive: 1, maxWaitTime: 0.1, idleCheckInterval: 0);
            $connector = new class implements ConnectorInterface {
                public function connect(): object
                {
                    throw new \RuntimeException("Boom");
                }
                public function disconnect(object $c): void
                {
                }
                public function isConnected(object $c): bool
                {
                    return true;
                }
                public function reset(object $c): void
                {
                }
            };

            $pool = new ConnectionPool($config, $connector);
            // This shouldn't crash, just log error internally
            $pool->init();
            $pool->close();
            $this->expectNotToPerformAssertions();
        });
    }

    public function testReturnFailure(): void
    {
        \Swoole\Coroutine\run(function () {
            $config = new PoolConfig(maxActive: 1, maxWaitTime: 0.1, idleCheckInterval: 0);
            $connector = new class implements ConnectorInterface {
                public function connect(): object
                {
                    return new \stdClass();
                }
                public function disconnect(object $c): void
                {
                }
                public function isConnected(object $c): bool
                {
                    return true;
                }
                public function reset(object $c): void
                {
                    throw new \RuntimeException("Reset failed");
                }
            };

            $pool = new ConnectionPool($config, $connector);
            $pool->init();
            $conn = $pool->borrow();

            // Return should catch the reset failure, disconnect, and decrement count
            $pool->return($conn);
            $pool->close();
            $this->expectNotToPerformAssertions();
        });
    }
}
