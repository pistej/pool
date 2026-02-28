<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Tests\Unit\ConnectionPool;

use PHPUnit\Framework\TestCase;
use Sfrpc\Pool\ConnectionPool\ConnectionPool;
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
                {}
                public function connect(): object
                {
                    $this->connects++;
                    return $this->conn; }
                public function disconnect(object $c): void
                {
                    $this->disconnects++; }
                public function isConnected(object $c): bool
                {
                    return !$this->simulateDead; }
                public function reset(object $c): void
                {}
            };

            $pool = new ConnectionPool($config, $connector);
            $pool->init(); // Connects = 1

            // Borrow successfully
            $conn = $pool->borrow();
            $pool->return($conn);

            // Now, simulate the connection dying while idle in the pool
            $connector->simulateDead = true;

            // When we borrow again, it should detect it's dead, disconnect it, and create a new one recursively
            // But wait, our dummy connector returns simulateDead = true always now, so if we try to recursive borrow it will fail infinite loop!
            // Let's make it only dead ONCE.
            $connector->simulateDead = false; // Reset first
            $pool->borrow(); // Just to get it out
            $pool->return($conn);

            // Now make it dead, but when it reconnects, make it alive again.
            $connector = new class ($dummyConnection) implements ConnectorInterface {
                public int $checks = 0;
                public function __construct(private object $conn)
                {}
                public function connect(): object
                {
                    return clone $this->conn; } // Give it a new instance
                public function disconnect(object $c): void
                {}
                public function isConnected(object $c): bool
                {
                    $this->checks++;
                    // Fail the first check (detecting dead), pass the second (new connection)
                    return $this->checks > 1;
                }
                public function reset(object $c): void
                {}
            };

            $pool2 = new ConnectionPool($config, $connector);
            $pool2->init();

            $newConn = $pool2->borrow();
            $this->assertNotSame($dummyConnection, $newConn); // It's a newly cloned one!

            $pool2->close();
        });
    }

    public function testIdleConnectionReaping(): void
    {
        \Swoole\Coroutine\run(function () {
            // Idle check every 0.1s. Max idle time is 0.2s. Min active is 1. Max active is 2.
            $config = new PoolConfig(minActive: 1, maxActive: 2, maxWaitTime: 0.1, maxIdleTime: 0.2, idleCheckInterval: 0);
            $dummyConnection = new \stdClass();

            $connector = new class ($dummyConnection) implements ConnectorInterface {
                public int $disconnects = 0;
                public function __construct(private object $conn)
                {}
                public function connect(): object
                {
                    return clone $this->conn; }
                public function disconnect(object $c): void
                {
                    $this->disconnects++; }
                public function isConnected(object $c): bool
                {
                    return true; }
                public function reset(object $c): void
                {}
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

    public function testReturnToClosedPool(): void
    {
        \Swoole\Coroutine\run(function () {
            $config = new PoolConfig(maxActive: 1, maxWaitTime: 0.1, idleCheckInterval: 0);
            $dummyConnection = new \stdClass();
            $connector = new class ($dummyConnection) implements ConnectorInterface {
                public int $disconnects = 0;
                public function __construct(private object $conn)
                {}
                public function connect(): object
                {
                    return $this->conn; }
                public function disconnect(object $c): void
                {
                    $this->disconnects++; }
                public function isConnected(object $c): bool
                {
                    return true; }
                public function reset(object $c): void
                {}
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
            $this->assertTrue(true);
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
            $this->assertTrue(true);
        });
    }
}
