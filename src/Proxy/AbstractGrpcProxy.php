<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Proxy;

use Sfrpc\Pool\ConnectionPool\ConnectionPool;

abstract class AbstractGrpcProxy
{
    public function __construct(protected readonly ConnectionPool $pool)
    {
    }

    /**
     * Executes the given callable in a Swoole Coroutine context.
     * If currently not in a coroutine, it will create one via \Swoole\Coroutine\run.
     *
     * @template T
     * @param callable(): T $callable
     * @return T
     * @throws \Throwable
     */
    protected function executeInContext(callable $callable): mixed
    {
        if (\Swoole\Coroutine::getCid() !== -1) {
            return $callable();
        }

        $result = null;
        $exception = null;
        \Swoole\Coroutine\run(function () use ($callable, &$result, &$exception) {
            try {
                $result = $callable();
            } catch (\Throwable $e) {
                $exception = $e;
            }
        });

        if ($exception !== null) {
            throw $exception;
        }

        /** @var T $result */
        return $result;
    }
}
