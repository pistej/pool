<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Grpc;

use Sfrpc\Pool\Grpc\Exception\GrpcException;
use Swoole\Coroutine\Http2\Client;
use Swoole\Http2\Request;

class BaseClient
{
    private ?Client $client = null;
    private bool $connected = false;

    /**
     * @param array<string, mixed> $swooleSettings
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly bool $ssl = false,
        private readonly array $swooleSettings = []
    ) {
    }

    public function connect(): bool
    {
        if ($this->connected && $this->client) {
            return true;
        }

        $this->client = new Client($this->host, $this->port, $this->ssl);
        $this->client->set(array_merge([
            'open_http2_protocol' => true,
        ], $this->swooleSettings));

        $this->connected = (bool) $this->client->connect();
        return $this->connected;
    }

    public function close(): void
    {
        if ($this->client) {
            $this->client->close();
            $this->client = null;
        }
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->client && $this->client->connected;
    }

    protected function _simpleRequest(
        string $method,
        \Google\Protobuf\Internal\Message $argument,
        string $deserializeClass,
        ?ClientContext $context = null
    ): \Google\Protobuf\Internal\Message {
        if (!$this->isConnected()) {
            if (!$this->connect()) {
                throw new GrpcException("Failed to connect to {$this->host}:{$this->port}");
            }
        }

        $context ??= new ClientContext();

        $request = new Request();
        $request->pipeline = false;
        $request->method = 'POST';
        $request->path = $method;
        $request->headers = [
            'content-type' => 'application/grpc',
            'te' => 'trailers',
        ];

        foreach ($context->getMetadata() as $key => $value) {
            $request->headers[$key] = $value;
        }

        // Pack the message (standard gRPC length-prefixed message)
        $payload = $argument->serializeToString();
        // 1 byte compressed flag, 4 bytes length
        $request->data = pack('CN', 0, strlen($payload)) . $payload;

        $timeout = $context->getTimeout();
        $clientId = $this->client->send($request);

        if ($clientId === false) {
            $this->connected = false;
            throw new GrpcException(sprintf("Send request failed, errCode: %s", (int) $this->client->errCode));
        }

        $response = $timeout > 0 ? $this->client->recv($timeout) : $this->client->recv();

        if (!$response) {
            $this->connected = false;
            throw new GrpcException(sprintf("Recv response failed, errCode: %s", (int) $this->client->errCode));
        }

        // Check HTTP status and gRPC status
        if ((int) $response->statusCode !== 200) {
            throw new GrpcException("HTTP error: " . (int) $response->statusCode);
        }

        /** @var array<string, mixed> $headers */
        $headers = $response->headers ?? [];

        $grpcStatus = (int) ($headers['grpc-status'] ?? 0);
        if ($grpcStatus !== 0) {
            $msg = (string) ($headers['grpc-message'] ?? "Unknown gRPC error");
            throw new GrpcException($msg, $grpcStatus);
        }

        // Unpack payload
        $data = (string) ($response->data ?? '');
        if (strlen($data) >= 5) {
            // Strip the 5 byte length-prefixed header
            $data = substr($data, 5);
        }

        /** @var \Google\Protobuf\Internal\Message $message */
        $message = new $deserializeClass();
        $message->mergeFromString($data);

        return $message;
    }
}
