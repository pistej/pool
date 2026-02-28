<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Tests\Unit\Grpc;

use PHPUnit\Framework\TestCase;
use Sfrpc\Pool\Grpc\ClientContext;

class ClientContextTest extends TestCase
{
    public function testContextOverrides(): void
    {
        $context = new ClientContext(['foo' => 'bar'], 2.0);
        $this->assertEquals(['foo' => 'bar'], $context->getMetadata());
        $this->assertEquals(2.0, $context->getTimeout());

        $newContext = $context->withMetadata(['baz' => 'qux']);
        $this->assertEquals(['foo' => 'bar', 'baz' => 'qux'], $newContext->getMetadata());

        $newContext2 = $newContext->withTimeout(5.5);
        $this->assertEquals(5.5, $newContext2->getTimeout());

        // original should be unchanged
        $this->assertEquals(2.0, $context->getTimeout());
    }
}
