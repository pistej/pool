<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Tests\Unit\Grpc\Exception;

use PHPUnit\Framework\TestCase;
use Sfrpc\Pool\Grpc\Exception\GrpcException;

class GrpcExceptionTest extends TestCase
{
    public function testGrpcExceptionInstantiation(): void
    {
        $exception = new GrpcException('Connection failed', 12);

        $this->assertSame('Connection failed', $exception->getMessage());
        $this->assertSame(12, $exception->getCode());
    }
}
