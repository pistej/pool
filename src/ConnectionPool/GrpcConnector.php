<?php

declare(strict_types=1);

namespace Sfrpc\Pool\ConnectionPool;

use Sfrpc\Pool\Grpc\BaseClient;

class GrpcConnector implements ConnectorInterface
{
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

    public function connect(): object
    {
        $client = new BaseClient($this->host, $this->port, $this->ssl, $this->swooleSettings);
        $client->connect();
        return $client;
    }

    /**
     * @param BaseClient|object $connection
     */
    public function disconnect(object $connection): void
    {
        if ($connection instanceof BaseClient) {
            $connection->close();
        }
    }

    /**
     * @param BaseClient|object $connection
     */
    public function isConnected(object $connection): bool
    {
        if ($connection instanceof BaseClient) {
            return $connection->isConnected();
        }
        return false;
    }

    public function reset(object $connection): void
    {
        // No reset needed for BaseClient, Swoole handles http2 multiplex frames
    }
}
