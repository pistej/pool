<?php

declare(strict_types=1);

namespace Sfrpc\Pool\ConnectionPool;

final class ConnectionWrapper
{
    public function __construct(
        private readonly object $connection,
        private float $lastUsedAt
    ) {
    }

    public function getConnection(): object
    {
        return $this->connection;
    }

    public function getLastUsedAt(): float
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(float $time): void
    {
        $this->lastUsedAt = $time;
    }
}
