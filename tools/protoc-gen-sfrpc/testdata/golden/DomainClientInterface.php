<?php

declare(strict_types=1);

namespace Wss\Tool\Grpc;

use Sfrpc\Pool\Grpc\ClientContext;

interface DomainClientInterface
{
    public function GetInfo(\Wss\Tool\Grpc\GetInfoRequest $request, ?ClientContext $context = null): \Wss\Tool\Grpc\DomainInfo;
}
