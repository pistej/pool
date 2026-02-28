<?php

declare(strict_types=1);

namespace Sfrpc\Pool\ConnectionPool;

final class PoolConfig
{
    public function __construct(
        public readonly int $minActive = 5,
        public readonly int $maxActive = 20,
        public readonly float $maxWaitTime = 5.0,
        public readonly float $maxIdleTime = 30.0,
        public readonly float $idleCheckInterval = 10.0
    ) {
    }
}
