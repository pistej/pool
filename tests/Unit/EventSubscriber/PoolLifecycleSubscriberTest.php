<?php

declare(strict_types=1);

namespace Sfrpc\Pool\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Sfrpc\Pool\ConnectionPool\ConnectionPool;
use Sfrpc\Pool\EventSubscriber\PoolLifecycleSubscriber;
use stdClass;

class PoolLifecycleSubscriberTest extends TestCase
{
    public function testOnWorkerStartedInitializesAllPools(): void
    {
        $pool1 = $this->createMock(ConnectionPool::class);
        $pool2 = $this->createMock(ConnectionPool::class);

        $pool1->expects($this->once())->method('init');
        $pool2->expects($this->once())->method('init');

        $subscriber = new PoolLifecycleSubscriber([$pool1, $pool2]);
        $event = new stdClass(); // Generic dummy event object

        $subscriber->onWorkerStarted($event);
    }

    public function testOnWorkerStoppedClosesAllPools(): void
    {
        $pool1 = $this->createMock(ConnectionPool::class);
        $pool2 = $this->createMock(ConnectionPool::class);

        $pool1->expects($this->once())->method('close');
        $pool2->expects($this->once())->method('close');

        $subscriber = new PoolLifecycleSubscriber([$pool1, $pool2]);
        $event = new stdClass();

        $subscriber->onWorkerStopped($event);
    }

    public function testOnWorkerExitedClosesAllPools(): void
    {
        $pool1 = $this->createMock(ConnectionPool::class);
        $pool2 = $this->createMock(ConnectionPool::class);

        $pool1->expects($this->once())->method('close');
        $pool2->expects($this->once())->method('close');

        $subscriber = new PoolLifecycleSubscriber([$pool1, $pool2]);
        $event = new stdClass();

        $subscriber->onWorkerExited($event);
    }

    public function testOnWorkerErroredClosesAllPools(): void
    {
        $pool1 = $this->createMock(ConnectionPool::class);
        $pool2 = $this->createMock(ConnectionPool::class);

        $pool1->expects($this->once())->method('close');
        $pool2->expects($this->once())->method('close');

        $subscriber = new PoolLifecycleSubscriber([$pool1, $pool2]);
        $event = new stdClass();

        $subscriber->onWorkerErrored($event);
    }
}
