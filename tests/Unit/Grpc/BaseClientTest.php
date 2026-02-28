<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Tests\Unit\Grpc;

use Google\Protobuf\Internal\Message;
use PHPUnit\Framework\TestCase;
use Sfrpc\Pool\Grpc\BaseClient;
use Sfrpc\Pool\Grpc\ClientContext;
use Swoole\Coroutine\Http2\Client;
use Swoole\Http2\Request;
use Swoole\Http2\Response;



class TestBaseClient extends BaseClient
{
    public ?Client $mockClient = null;

    public function __construct(string $host, int $port)
    {
        parent::__construct($host, $port);
    }

    // Override connect to inject the mock client instead of a real one
    public function connect(): bool
    {
        if ($this->mockClient) {
            $refClass = new \ReflectionClass(BaseClient::class);
            $propClient = $refClass->getProperty('client');
            $propClient->setAccessible(true);
            $propClient->setValue($this, $this->mockClient);

            $propConnected = $refClass->getProperty('connected');
            $propConnected->setAccessible(true);
            $propConnected->setValue($this, true);

            return true;
        }

        return parent::connect();
    }

    public function callSimpleRequest(string $method, Message $argument, string $deserializeClass, ?ClientContext $context = null): Message
    {
        return $this->_simpleRequest($method, $argument, $deserializeClass, $context);
    }
}

class BaseClientTest extends TestCase
{
    public function testSimpleRequestSuccessfullyPackagesData(): void
    {
        // We cannot easily mock Swoole classes in pure PHPUnit if the extension
        // is strict about them, but we can try building a mock object matching its signature.

        // We'll create a mock Swoole Client built purely for testing the frame logic.
        $mockClient = $this->createMock(Client::class);
        $mockClient->connected = true;

        $targetPath = '/proto.Greeter/SayHello';

        $mockClient->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (Request $request) use ($targetPath) {
                // Assert the request being sent uses the correct Http2 Request class
                $this->assertInstanceOf(Request::class, $request);
                $this->assertSame('POST', $request->method);
                $this->assertSame($targetPath, $request->path);
                $this->assertArrayHasKey('content-type', $request->headers);
                $this->assertSame('application/grpc', $request->headers['content-type']);
                $this->assertArrayHasKey('foo-header', $request->headers);
                $this->assertSame('bar-value', $request->headers['foo-header']);

                // Verify the 5-byte length preamble (0 byte for compressed + 4 bytes length)
                $data = $request->data;
                $this->assertGreaterThanOrEqual(5, strlen($data));

                return 1; // Return a fake stream ID
            });

        $mockResponse = new Response();
        $mockResponse->statusCode = 200;
        $mockResponse->headers = [
            'grpc-status' => '0',
            'grpc-message' => 'OK'
        ];
        // Fake encoded protobuf response (empty message)
        $mockResponse->data = pack('CN', 0, 0) . '';

        $mockClient->expects($this->once())
            ->method('recv')
            ->willReturn($mockResponse);

        $baseClient = new TestBaseClient('127.0.0.1', 8080);
        $baseClient->mockClient = $mockClient;

        // Force connection logic
        $baseClient->connect();

        $context = new ClientContext();
        $context = $context->withMetadata(['foo-header' => 'bar-value']);

        $message = new \Google\Protobuf\Any();

        $responseMessage = $baseClient->callSimpleRequest(
            $targetPath,
            $message,
            \Google\Protobuf\Any::class,
            $context
        );

        $this->assertInstanceOf(\Google\Protobuf\Any::class, $responseMessage);
    }

    public function testSimpleRequestHandlesHttpError(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->connected = true;
        $mockClient->expects($this->once())->method('send')->willReturn(1);

        $mockResponse = new Response();
        $mockResponse->statusCode = 500;

        $mockClient->expects($this->once())->method('recv')->willReturn($mockResponse);

        $baseClient = new TestBaseClient('127.0.0.1', 8080);
        $baseClient->mockClient = $mockClient;
        $baseClient->connect();

        $this->expectException(\Sfrpc\Pool\Grpc\Exception\GrpcException::class);
        $this->expectExceptionMessage('HTTP error: 500');

        $baseClient->callSimpleRequest('/test', new \Google\Protobuf\Any(), \Google\Protobuf\Any::class);
    }

    public function testSimpleRequestHandlesGrpcError(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->connected = true;
        $mockClient->expects($this->once())->method('send')->willReturn(1);

        $mockResponse = new Response();
        $mockResponse->statusCode = 200;
        $mockResponse->headers = [
            'grpc-status' => '14',
            'grpc-message' => 'Unavailable'
        ];

        $mockClient->expects($this->once())->method('recv')->willReturn($mockResponse);

        $baseClient = new TestBaseClient('127.0.0.1', 8080);
        $baseClient->mockClient = $mockClient;
        $baseClient->connect();

        $this->expectException(\Sfrpc\Pool\Grpc\Exception\GrpcException::class);
        $this->expectExceptionMessage('Unavailable');
        $this->expectExceptionCode(14);

        $baseClient->callSimpleRequest('/test', new \Google\Protobuf\Any(), \Google\Protobuf\Any::class);
    }

    public function testSimpleRequestHandlesRecvFailure(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->connected = true;
        $mockClient->expects($this->once())->method('send')->willReturn(1);
        $mockClient->errCode = 111;

        $mockClient->expects($this->once())->method('recv')->willReturn(false);

        $baseClient = new TestBaseClient('127.0.0.1', 8080);
        $baseClient->mockClient = $mockClient;
        $baseClient->connect();

        $this->expectException(\Sfrpc\Pool\Grpc\Exception\GrpcException::class);
        $this->expectExceptionMessage('Recv response failed, errCode: 111');

        $baseClient->callSimpleRequest('/test', new \Google\Protobuf\Any(), \Google\Protobuf\Any::class);
    }

    public function testBaseClientConnectionLifecycle(): void
    {
        \Swoole\Coroutine\run(function () {
            // Test real connect failure with short timeout
            $client = new BaseClient('127.0.0.1', 9999, false, ['connect_timeout' => 0.1]);
            $this->assertFalse($client->connect());
            $this->assertFalse($client->isConnected());

            $client->close();
            $this->assertFalse($client->isConnected());
        });
    }
}
