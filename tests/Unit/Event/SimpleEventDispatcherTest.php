<?php

declare(strict_types=1);

namespace Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Zestic\GraphQL\AuthComponent\Event\SimpleEventDispatcher;

class SimpleEventDispatcherTest extends TestCase
{
    private ListenerProviderInterface $listenerProvider;
    private SimpleEventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->listenerProvider = $this->createMock(ListenerProviderInterface::class);
        $this->dispatcher = new SimpleEventDispatcher($this->listenerProvider);
    }

    public function testDispatchWithNoListeners()
    {
        $event = new TestDispatchEvent('test data');

        $this->listenerProvider
            ->expects($this->once())
            ->method('getListenersForEvent')
            ->with($event)
            ->willReturn([]);

        $result = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $result);
    }

    public function testDispatchWithSingleListener()
    {
        $event = new TestDispatchEvent('test data');
        $listenerCalled = false;

        $listener = function (TestDispatchEvent $event) use (&$listenerCalled) {
            $listenerCalled = true;
        };

        $this->listenerProvider
            ->expects($this->once())
            ->method('getListenersForEvent')
            ->with($event)
            ->willReturn([$listener]);

        $result = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertTrue($listenerCalled);
    }

    public function testDispatchWithMultipleListeners()
    {
        $event = new TestDispatchEvent('test data');
        $callOrder = [];

        $listener1 = function (TestDispatchEvent $event) use (&$callOrder) {
            $callOrder[] = 'listener1';
        };

        $listener2 = function (TestDispatchEvent $event) use (&$callOrder) {
            $callOrder[] = 'listener2';
        };

        $listener3 = function (TestDispatchEvent $event) use (&$callOrder) {
            $callOrder[] = 'listener3';
        };

        $this->listenerProvider
            ->expects($this->once())
            ->method('getListenersForEvent')
            ->with($event)
            ->willReturn([$listener1, $listener2, $listener3]);

        $result = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertEquals(['listener1', 'listener2', 'listener3'], $callOrder);
    }

    public function testDispatchWithStoppableEventThatDoesNotStop()
    {
        $event = new TestStoppableEvent('test data', false);
        $callOrder = [];

        $listener1 = function (TestStoppableEvent $event) use (&$callOrder) {
            $callOrder[] = 'listener1';
        };

        $listener2 = function (TestStoppableEvent $event) use (&$callOrder) {
            $callOrder[] = 'listener2';
        };

        $this->listenerProvider
            ->expects($this->once())
            ->method('getListenersForEvent')
            ->with($event)
            ->willReturn([$listener1, $listener2]);

        $result = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertEquals(['listener1', 'listener2'], $callOrder);
    }

    public function testDispatchWithStoppableEventThatStopsImmediately()
    {
        $event = new TestStoppableEvent('test data', true);
        $callOrder = [];

        $listener1 = function (TestStoppableEvent $event) use (&$callOrder) {
            $callOrder[] = 'listener1';
        };

        $listener2 = function (TestStoppableEvent $event) use (&$callOrder) {
            $callOrder[] = 'listener2';
        };

        $this->listenerProvider
            ->expects($this->once())
            ->method('getListenersForEvent')
            ->with($event)
            ->willReturn([$listener1, $listener2]);

        $result = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertEmpty($callOrder); // No listeners should be called
    }

    public function testDispatchWithStoppableEventThatStopsAfterFirstListener()
    {
        $event = new TestStoppableEvent('test data', false);
        $callOrder = [];

        $listener1 = function (TestStoppableEvent $event) use (&$callOrder) {
            $callOrder[] = 'listener1';
            $event->stopPropagation(); // Stop after first listener
        };

        $listener2 = function (TestStoppableEvent $event) use (&$callOrder) {
            $callOrder[] = 'listener2';
        };

        $listener3 = function (TestStoppableEvent $event) use (&$callOrder) {
            $callOrder[] = 'listener3';
        };

        $this->listenerProvider
            ->expects($this->once())
            ->method('getListenersForEvent')
            ->with($event)
            ->willReturn([$listener1, $listener2, $listener3]);

        $result = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertEquals(['listener1'], $callOrder); // Only first listener should be called
    }

    public function testDispatchWithListenerThatThrowsException()
    {
        $event = new TestDispatchEvent('test data');

        $listener = function (TestDispatchEvent $event) {
            throw new \RuntimeException('Listener error');
        };

        $this->listenerProvider
            ->expects($this->once())
            ->method('getListenersForEvent')
            ->with($event)
            ->willReturn([$listener]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Listener error');

        $this->dispatcher->dispatch($event);
    }

    public function testDispatchWithCallableObjectListener()
    {
        $event = new TestDispatchEvent('test data');
        $callableListener = new CallableDispatchListener();

        $this->listenerProvider
            ->expects($this->once())
            ->method('getListenersForEvent')
            ->with($event)
            ->willReturn([$callableListener]);

        $result = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertTrue($callableListener->wasCalled());
    }

    public function testDispatchWithIterableListeners()
    {
        $event = new TestDispatchEvent('test data');
        $callOrder = [];

        $listener1 = function (TestDispatchEvent $event) use (&$callOrder) {
            $callOrder[] = 'listener1';
        };

        $listener2 = function (TestDispatchEvent $event) use (&$callOrder) {
            $callOrder[] = 'listener2';
        };

        // Return a generator instead of array
        $listenerGenerator = function () use ($listener1, $listener2) {
            yield $listener1;
            yield $listener2;
        };

        $this->listenerProvider
            ->expects($this->once())
            ->method('getListenersForEvent')
            ->with($event)
            ->willReturn($listenerGenerator());

        $result = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertEquals(['listener1', 'listener2'], $callOrder);
    }
}

// Test event classes
class TestDispatchEvent
{
    public function __construct(public readonly string $data) {}
}

class TestStoppableEvent implements StoppableEventInterface
{
    private bool $propagationStopped;

    public function __construct(
        public readonly string $data,
        bool $initiallyStoppedPropagation = false
    ) {
        $this->propagationStopped = $initiallyStoppedPropagation;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}

// Test callable listener class
class CallableDispatchListener
{
    private bool $called = false;

    public function __invoke(TestDispatchEvent $event): void
    {
        $this->called = true;
    }

    public function wasCalled(): bool
    {
        return $this->called;
    }
}
