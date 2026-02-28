<?php

declare(strict_types=1);

namespace Sfrpc\Pool\EventSubscriber;

use Sfrpc\Pool\ConnectionPool\ConnectionPool;

class PoolLifecycleSubscriber
{
    /**
     * @param iterable<ConnectionPool> $pools
     */
    public function __construct(private readonly iterable $pools)
    {
    }

    public function onWorkerStarted(object $event): void
    {
        foreach ($this->pools as $pool) {
            $pool->init();
        }
    }
}
