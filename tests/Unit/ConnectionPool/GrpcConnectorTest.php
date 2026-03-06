<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Tests\Unit\ConnectionPool;

use PHPUnit\Framework\TestCase;
use Sfrpc\Pool\ConnectionPool\GrpcConnector;
use Sfrpc\Pool\Grpc\BaseClient;

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

        $dummyClient = new class ('127.0.0.1', 8080) extends BaseClient {
            public bool $closedCalled = false;
            public function close(): void
            {
                $this->closedCalled = true;
            }
        };

        $connector->disconnect($dummyClient);
        $this->assertTrue($dummyClient->closedCalled);
    }

    public function testIsConnectedChecksBaseClient(): void
    {
        $connector = new GrpcConnector('127.0.0.1', 8080);

        $mockClient = $this->createMock(BaseClient::class);
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
        $this->expectNotToPerformAssertions();
    }
}
