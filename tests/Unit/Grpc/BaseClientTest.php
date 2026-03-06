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
            ->willReturnCallback(function (Request $request) use ($targetPath): int {
                // Assert the request being sent uses the correct Http2 Request class
                $this->assertInstanceOf(Request::class, $request);
                $this->assertSame('POST', $request->method);
                $this->assertSame($targetPath, $request->path);

                /** @var array<string, mixed> $headers */
                $headers = $request->headers;
                $this->assertArrayHasKey('content-type', $headers);
                $this->assertSame('application/grpc', $headers['content-type']);
                $this->assertArrayHasKey('foo-header', $headers);
                $this->assertSame('bar-value', $headers['foo-header']);

                // Verify the 5-byte length preamble (0 byte for compressed + 4 bytes length)
                $data = (string) $request->data;
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

        $baseClient = new class ('127.0.0.1', 8080) extends BaseClient {
            public ?Client $mockClient = null;

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

            public function callSimpleRequest(
                string $method,
                Message $arg,
                string $class,
                ?ClientContext $ctx = null
            ): Message {
                return $this->simpleRequest($method, $arg, $class, $ctx);
            }
        };
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

        $baseClient = new class ('127.0.0.1', 8080) extends BaseClient {
            public ?Client $mockClient = null;

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

            public function callSimpleRequest(string $m, Message $a, string $c): Message
            {
                return $this->simpleRequest($m, $a, $c);
            }
        };
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

        $baseClient = new class ('127.0.0.1', 8080) extends BaseClient {
            public ?Client $mockClient = null;

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

            public function callSimpleRequest(string $m, Message $a, string $c): Message
            {
                return $this->simpleRequest($m, $a, $c);
            }
        };
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

        $baseClient = new class ('127.0.0.1', 8080) extends BaseClient {
            public ?Client $mockClient = null;

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

            public function callSimpleRequest(string $m, Message $a, string $c): Message
            {
                return $this->simpleRequest($m, $a, $c);
            }
        };
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
