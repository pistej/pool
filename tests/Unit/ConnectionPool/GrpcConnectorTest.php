<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Tests\Unit\ConnectionPool;

use PHPUnit\Framework\TestCase;
use Sfrpc\Pool\ConnectionPool\GrpcConnector;
use Sfrpc\Pool\Grpc\BaseClient;

// We need a dummy subclass to mock BaseClient easily since it doesn't have an interface
class DummyBaseClient extends BaseClient
{
    public function __construct()
    {
    }
    public function connect(): bool
    {
        return true;
    }
    public function close(): void
    {
    }
    public function isConnected(): bool
    {
        return true;
    }
}

class GrpcConnectorTest extends TestCase
{
    public function testConnectReturnsBaseClientInstance(): void
    {
        \Swoole\Coroutine\run(function () {
            $connector = new GrpcConnector('127.0.0.1', 8080, false, ['timeout' => 5]);
            $client = $connector->connect();

            $this->assertInstanceOf(BaseClient::class, $client);
        });
    }

    public function testDisconnectClosesClient(): void
    {
        $connector = new GrpcConnector('127.0.0.1', 8080);

        $mockClient = $this->createMock(DummyBaseClient::class);
        $mockClient->expects($this->once())->method('close');

        $connector->disconnect($mockClient);
    }

    public function testIsConnectedChecksBaseClient(): void
    {
        $connector = new GrpcConnector('127.0.0.1', 8080);

        $mockClient = $this->createMock(DummyBaseClient::class);
        $mockClient->expects($this->once())
            ->method('isConnected')
            ->willReturn(true);

        $this->assertTrue($connector->isConnected($mockClient));

        // Passing a random object should return false
        $this->assertFalse($connector->isConnected(new \stdClass()));
    }

    public function testResetDoesNothing(): void
    {
        $connector = new GrpcConnector('127.0.0.1', 8080);
        $client = new \stdClass();

        // Since reset is empty, this just shouldn't throw an error
        $connector->reset($client);
        $this->assertTrue(true);
    }
}
