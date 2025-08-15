<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Event;

use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Simple PSR-14 compliant listener provider implementation
 *
 * This is a basic implementation that stores listeners in memory.
 * For production use, consider using a more robust implementation.
 */
class SimpleListenerProvider implements ListenerProviderInterface
{
    /**
     * @var array<string, callable[]>
     */
    private array $listeners = [];

    /**
     * Add a listener for a specific event type
     *
     * @param string $eventType The fully qualified class name of the event
     * @param callable $listener The listener callable
     */
    public function addListener(string $eventType, callable $listener): void
    {
        $this->listeners[$eventType][] = $listener;
    }

    /**
     * @return iterable<callable>
     */
    public function getListenersForEvent(object $event): iterable
    {
        $eventType = get_class($event);

        return $this->listeners[$eventType] ?? [];
    }
}
