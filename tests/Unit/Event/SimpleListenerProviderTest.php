<?php

declare(strict_types=1);

namespace Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Event\SimpleListenerProvider;

class SimpleListenerProviderTest extends TestCase
{
    private SimpleListenerProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new SimpleListenerProvider();
    }

    public function testAddListenerAndGetListenersForEvent()
    {
        $event = new TestEvent('test data');
        $listener1Called = false;
        $listener2Called = false;

        $listener1 = function (TestEvent $event) use (&$listener1Called) {
            $listener1Called = true;
        };

        $listener2 = function (TestEvent $event) use (&$listener2Called) {
            $listener2Called = true;
        };

        // Add listeners
        $this->provider->addListener(TestEvent::class, $listener1);
        $this->provider->addListener(TestEvent::class, $listener2);

        // Get listeners for the event
        $listeners = $this->provider->getListenersForEvent($event);
        $listenersArray = iterator_to_array($listeners);

        $this->assertCount(2, $listenersArray);
        $this->assertSame($listener1, $listenersArray[0]);
        $this->assertSame($listener2, $listenersArray[1]);

        // Verify listeners can be called
        foreach ($listenersArray as $listener) {
            $listener($event);
        }

        $this->assertTrue($listener1Called);
        $this->assertTrue($listener2Called);
    }

    public function testGetListenersForEventWithNoListeners()
    {
        $event = new TestEvent('test data');
        $listeners = $this->provider->getListenersForEvent($event);
        $listenersArray = iterator_to_array($listeners);

        $this->assertEmpty($listenersArray);
    }

    public function testGetListenersForEventWithDifferentEventTypes()
    {
        $testEvent = new TestEvent('test data');
        $anotherEvent = new AnotherTestEvent('another data');

        $testListener = function (TestEvent $event) {};
        $anotherListener = function (AnotherTestEvent $event) {};

        $this->provider->addListener(TestEvent::class, $testListener);
        $this->provider->addListener(AnotherTestEvent::class, $anotherListener);

        // Get listeners for TestEvent
        $testListeners = iterator_to_array($this->provider->getListenersForEvent($testEvent));
        $this->assertCount(1, $testListeners);
        $this->assertSame($testListener, $testListeners[0]);

        // Get listeners for AnotherTestEvent
        $anotherListeners = iterator_to_array($this->provider->getListenersForEvent($anotherEvent));
        $this->assertCount(1, $anotherListeners);
        $this->assertSame($anotherListener, $anotherListeners[0]);
    }

    public function testMultipleListenersForSameEventType()
    {
        $event = new TestEvent('test data');
        $callOrder = [];

        $listener1 = function (TestEvent $event) use (&$callOrder) {
            $callOrder[] = 'listener1';
        };

        $listener2 = function (TestEvent $event) use (&$callOrder) {
            $callOrder[] = 'listener2';
        };

        $listener3 = function (TestEvent $event) use (&$callOrder) {
            $callOrder[] = 'listener3';
        };

        // Add listeners in specific order
        $this->provider->addListener(TestEvent::class, $listener1);
        $this->provider->addListener(TestEvent::class, $listener2);
        $this->provider->addListener(TestEvent::class, $listener3);

        $listeners = iterator_to_array($this->provider->getListenersForEvent($event));
        $this->assertCount(3, $listeners);

        // Verify order is preserved
        foreach ($listeners as $listener) {
            $listener($event);
        }

        $this->assertEquals(['listener1', 'listener2', 'listener3'], $callOrder);
    }

    public function testAddListenerWithCallableObject()
    {
        $event = new TestEvent('test data');
        $callableObject = new CallableTestClass();

        $this->provider->addListener(TestEvent::class, $callableObject);

        $listeners = iterator_to_array($this->provider->getListenersForEvent($event));
        $this->assertCount(1, $listeners);
        $this->assertSame($callableObject, $listeners[0]);

        // Verify callable object can be invoked
        $listeners[0]($event);
        $this->assertTrue($callableObject->wasCalled());
    }

    public function testAddListenerWithStaticMethod()
    {
        $event = new TestEvent('test data');
        $staticCallable = [StaticTestClass::class, 'handleEvent'];

        $this->provider->addListener(TestEvent::class, $staticCallable);

        $listeners = iterator_to_array($this->provider->getListenersForEvent($event));
        $this->assertCount(1, $listeners);

        // Verify static method can be called
        StaticTestClass::reset();
        $listeners[0]($event);
        $this->assertTrue(StaticTestClass::wasCalled());
    }
}

// Test event classes
class TestEvent
{
    public function __construct(public readonly string $data)
    {
    }
}

class AnotherTestEvent
{
    public function __construct(public readonly string $data)
    {
    }
}

// Test callable class
class CallableTestClass
{
    private bool $called = false;

    public function __invoke(TestEvent $event): void
    {
        $this->called = true;
    }

    public function wasCalled(): bool
    {
        return $this->called;
    }
}

// Test static class
class StaticTestClass
{
    private static bool $called = false;

    public static function handleEvent(TestEvent $event): void
    {
        self::$called = true;
    }

    public static function wasCalled(): bool
    {
        return self::$called;
    }

    public static function reset(): void
    {
        self::$called = false;
    }
}
