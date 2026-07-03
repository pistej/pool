<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Tests\Fixtures;

/**
 * Represents an interface that is *not* part of the sfrpc generated client
 * contract (e.g. an app-level interface the generated proxy happens to also
 * implement, or a system interface). It must never be aliased by
 * ProxyInterfaceAliasPass (see H2).
 */
interface DummyUnrelatedInterface
{
}
