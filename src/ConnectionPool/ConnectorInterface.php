<?php

declare(strict_types=1);

namespace Sfrpc\Pool\ConnectionPool;

interface ConnectorInterface
{
    /**
     * Connect and return the connection instance.
     * @return object
     */
    public function connect(): object;

    /**
     * Close the connection.
     * @param object $connection
     */
    public function disconnect(object $connection): void;

    /**
     * Check if the connection is alive.
     * @param object $connection
     * @return bool
     */
    public function isConnected(object $connection): bool;

    /**
     * Reset the connection if necessary before going into the pool.
     * @param object $connection
     */
    public function reset(object $connection): void;
}
