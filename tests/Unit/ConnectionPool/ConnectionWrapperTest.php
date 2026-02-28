<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Tests\Unit\ConnectionPool;

use PHPUnit\Framework\TestCase;
use Sfrpc\Pool\ConnectionPool\ConnectionWrapper;

class ConnectionWrapperTest extends TestCase
{
    public function testConnectionWrapperStoresLogic(): void
    {
        $dummyConnection = new \stdClass();
        $dummyConnection->testId = 123;

        $wrapper = new ConnectionWrapper($dummyConnection, 1000.0);

        $this->assertSame($dummyConnection, $wrapper->getConnection());
        $this->assertSame(1000.0, $wrapper->getLastUsedAt());

        $wrapper->setLastUsedAt(2000.0);
        $this->assertSame(2000.0, $wrapper->getLastUsedAt());
    }
}
