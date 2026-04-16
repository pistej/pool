<?php

declare(strict_types=1);

namespace Sfrpc\Pool\EventSubscriber;

use Sfrpc\Pool\ConnectionPool\ConnectionPool;

readonly class PoolLifecycleSubscriber
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

    public function onWorkerStopped(object $event): void
    {
        foreach ($this->pools as $pool) {
            $pool->close();
        }
    }

    public function onWorkerExited(object $event): void
    {
        foreach ($this->pools as $pool) {
            $pool->close();
        }
    }

    public function onWorkerErrored(object $event): void
    {
        foreach ($this->pools as $pool) {
            $pool->close();
        }
    }
}
