<?php

declare(strict_types=1);

namespace Sfrpc\Pool\ConnectionPool;

final readonly class PoolConfig
{
    public function __construct(
        public int $minActive = 0,
        public int $maxActive = 10,
        public float $maxWaitTime = 5.0,
        public float $maxIdleTime = 30.0,
        public float $idleCheckInterval = 10.0,
        public bool $debugLogs = false
    ) {
    }
}
