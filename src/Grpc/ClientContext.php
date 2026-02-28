<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Grpc;

class ClientContext
{
    /**
     * @param array<string, string> $metadata
     * @param float $timeout Timeout in seconds
     */
    public function __construct(
        private array $metadata = [],
        private float $timeout = -1.0
    ) {
    }

    /** @return array<string, string> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * @param array<string, string> $metadata
     */
    public function withMetadata(array $metadata): self
    {
        $clone = clone $this;
        $clone->metadata = array_merge($this->metadata, $metadata);
        return $clone;
    }

    public function withTimeout(float $timeout): self
    {
        $clone = clone $this;
        $clone->timeout = $timeout;
        return $clone;
    }
}
