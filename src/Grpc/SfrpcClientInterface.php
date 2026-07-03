<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Grpc;

/**
 * Marker interface extended by every generated `*ClientInterface`.
 *
 * `ProxyInterfaceAliasPass` uses this (via `is_subclass_of()`) to reliably
 * identify which of a proxy's interfaces are actual sfrpc-generated client
 * contracts that should be aliased, instead of relying on a naming
 * convention (e.g. a `*ClientInterface` suffix), which host applications can
 * easily also use for their own, unrelated interfaces.
 */
interface SfrpcClientInterface
{
}
