<?php

declare(strict_types=1);

namespace Wss\Tool\Grpc;

use Sfrpc\Pool\Grpc\BaseClient;
use Sfrpc\Pool\Grpc\ClientContext;

class DomainClient extends BaseClient implements DomainClientInterface
{
    public function GetInfo(\Wss\Tool\Grpc\GetInfoRequest $request, ?ClientContext $context = null): \Wss\Tool\Grpc\DomainInfo
    {
        return $this->simpleRequest('/fastApi.Domain/GetInfo', $request, \Wss\Tool\Grpc\DomainInfo::class, $context);
    }
}
