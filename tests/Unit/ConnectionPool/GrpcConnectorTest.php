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
        // A plain listening socket is enough for the TCP handshake performed by
        // BaseClient::connect() to succeed, we don't need to speak HTTP/2 here.
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($server, "Failed to start test TCP listener: {$errstr}");
        $address = stream_socket_get_name($server, false);
        $this->assertNotFalse($address, 'Failed to resolve the test TCP listener address');
        $port = (int) substr($address, strrpos($address, ':') + 1);

        try {
            \Swoole\Coroutine\run(function () use ($port) {
                $connector = new GrpcConnector('127.0.0.1', $port, false, ['timeout' => 5]);
                $client = $connector->connect();

                $this->assertInstanceOf(BaseClient::class, $client);
            });
        } finally {
            fclose($server);
        }
    }

    public function testConnectThrowsWhenTcpConnectFails(): void
    {
        \Swoole\Coroutine\run(function () {
            // Nothing is listening on this port, so BaseClient::connect() returns false.
            // GrpcConnector::connect() must not silently hand back a dead client (C2).
            $connector = new GrpcConnector('127.0.0.1', 9999, false, ['connect_timeout' => 0.1]);

            $caught = false;
            try {
                $connector->connect();
            } catch (\RuntimeException $e) {
                $caught = true;
                $this->assertSame('Failed to connect to 127.0.0.1:9999', $e->getMessage());
            }
            $this->assertTrue($caught, 'Expected RuntimeException was not thrown');
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
