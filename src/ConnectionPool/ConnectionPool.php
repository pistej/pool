<?php

declare(strict_types=1);

namespace Sfrpc\Pool\ConnectionPool;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Timer;

class ConnectionPool
{
    private Channel $pool;
    private int $connectionCount = 0;
    private bool $closed = false;
    private int $checkTimerId = 0;
    private LoggerInterface $logger;

    public function __construct(
        private readonly PoolConfig $config,
        private readonly ConnectorInterface $connector,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->pool = new Channel($this->config->maxActive);
    }

    public function init(): void
    {
        $this->debug("Initializing pool with min active: {$this->config->minActive}");
        Coroutine::create(function () {
            for ($i = 0; $i < $this->config->minActive; $i++) {
                $this->createConnection();
            }
        });

        if ($this->config->idleCheckInterval > 0) {
            $this->checkTimerId = Timer::tick(
                (int) ($this->config->idleCheckInterval * 1000),
                $this->checkIdleConnections(...)
            ) ?: 0;
        }
    }

    public function borrow(): object
    {
        if ($this->closed) {
            throw new \RuntimeException("Pool is closed");
        }

        $connection = null;
        if ($this->pool->isEmpty() && $this->connectionCount < $this->config->maxActive) {
            $this->createConnection();
        }

        $wrapper = $this->pool->pop($this->config->maxWaitTime);
        if ($wrapper === false) {
            $this->debug("Pool timeout after {$this->config->maxWaitTime}s, no connection available");
            throw new \RuntimeException("Pool timeout after {$this->config->maxWaitTime}s, no connection available");
        }

        /** @var ConnectionWrapper $wrapper */
        $connection = $wrapper->getConnection();
        $this->debug("Borrowed connection. Available in pool: " . $this->pool->length());

        if (!$this->connector->isConnected($connection)) {
            $this->connector->disconnect($connection);
            $this->connectionCount--;
            $this->logger->warning("Borrowed connection was dead, creating new one.");

            // Re-borrow recursively
            return $this->borrow();
        }
        //todo: do we need reset? is in return

        return $connection;
    }

    public function return(object $connection): void
    {
        if ($this->closed) {
            $this->connector->disconnect($connection);
            $this->connectionCount--;
            return;
        }

        try {
            $this->connector->reset($connection);
            $wrapper = new ConnectionWrapper($connection, microtime(true));
            if (!$this->pool->push($wrapper, 0.001)) {
                // If the channel is full (e.g., config changes, or returned too many), we drop it.
                $this->debug("Pool full, dropping returned connection");
                $this->removeConnection($connection);
            } else {
                $this->debug("Returned connection. Available in pool: " . $this->pool->length());
            }
        } catch (\Throwable $e) {
            $this->removeConnection($connection);
            $this->logger->error("Failed to return connection: " . $e->getMessage());
        }
    }

    public function close(): void
    {
        $this->debug("Closing pool");
        $this->closed = true;
        if ($this->checkTimerId > 0) {
            Timer::clear($this->checkTimerId);
        }

        Coroutine::create(function () {
            while (!$this->pool->isEmpty()) {
                /** @var ConnectionWrapper $wrapper */
                $wrapper = $this->pool->pop(0.001);
                if ($wrapper) {
                    $this->removeConnection($wrapper->getConnection());
                }
            }
            $this->pool->close();
        });
    }

    private function createConnection(): void
    {
        try {
            $connection = $this->connector->connect();
            $this->connectionCount++;
            $wrapper = new ConnectionWrapper($connection, microtime(true));
            $this->pool->push($wrapper, 0.001);
            $this->debug("Created new connection. Total connections: {$this->connectionCount}");
        } catch (\Throwable $e) {
            $this->logger->error("Failed to create connection: " . $e->getMessage());
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    private function checkIdleConnections(): void
    {
        if ($this->closed || $this->pool->isEmpty()) {
            return;
        }

        $time = microtime(true);
        $count = $this->pool->length();
        $checked = 0;

        while ($checked < $count && $this->connectionCount > $this->config->minActive) {
            /** @var ConnectionWrapper|false $wrapper */
            $wrapper = $this->pool->pop(0.001);
            if ($wrapper === false) {
                break;
            }

            if (($time - $wrapper->getLastUsedAt()) > $this->config->maxIdleTime) {
                $this->removeConnection($wrapper->getConnection());
                $this->debug("Closed idle connection. Total connections: {$this->connectionCount}");
            } else {
                $this->pool->push($wrapper, 0.001);
            }

            $checked++;
        }
    }

    private function removeConnection(object $connection): void
    {
        $this->connectionCount--;
        $this->debug("Removing connection. Remaining: {$this->connectionCount}");
        Coroutine::create(function () use ($connection) {
            try {
                $this->connector->disconnect($connection);
            } catch (\Throwable $e) {
                // Ignore this exception.
            }
        });
    }

    private function debug(string $message): void
    {
        if ($this->config->debugLogs) {
            $this->logger->debug($message);
        }
    }
}
