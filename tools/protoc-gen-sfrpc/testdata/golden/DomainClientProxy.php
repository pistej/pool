<?php

declare(strict_types=1);

namespace Wss\Tool\Grpc;

use Sfrpc\Pool\Proxy\AbstractGrpcProxy;
use Sfrpc\Pool\Grpc\ClientContext;
use Sfrpc\Pool\Grpc\BaseClient;

class DomainClientProxy extends AbstractGrpcProxy implements DomainClientInterface
{
    public function GetInfo(\Wss\Tool\Grpc\GetInfoRequest $request, ?ClientContext $context = null): \Wss\Tool\Grpc\DomainInfo
    {
        return $this->executeInContext(function () use ($request, $context) {
            /** @var BaseClient $client */
            $client = $this->pool->borrow();
            try {
                return $client->simpleRequest('/fastApi.Domain/GetInfo', $request, \Wss\Tool\Grpc\DomainInfo::class, $context);
            } finally {
                $this->pool->return($client);
            }
        });
    }
}
