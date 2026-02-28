<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Proxy;

use Sfrpc\Pool\ConnectionPool\ConnectionPool;

abstract class AbstractGrpcProxy
{
    public function __construct(protected readonly ConnectionPool $pool)
    {
    }
}
