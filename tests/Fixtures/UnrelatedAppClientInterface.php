<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Tests\Fixtures;

/**
 * Simulates a naming collision: a host-application interface that happens
 * to also end in "ClientInterface" (e.g. a payment gateway client) but has
 * nothing to do with sfrpc-generated gRPC clients. It must never be aliased
 * by ProxyInterfaceAliasPass, even though a naive suffix check would match
 * it (H2).
 */
interface UnrelatedAppClientInterface
{
}
