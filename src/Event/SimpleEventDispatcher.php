<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Event;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Simple PSR-14 compliant event dispatcher implementation
 *
 * This is a basic implementation that can be used when no other event dispatcher
 * is available. For production use, consider using a more robust implementation
 * like Symfony EventDispatcher or similar.
 */
class SimpleEventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private readonly ListenerProviderInterface $listenerProvider
    ) {
    }

    public function dispatch(object $event): object
    {
        $listeners = $this->listenerProvider->getListenersForEvent($event);

        foreach ($listeners as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }

            $listener($event);
        }

        return $event;
    }
}
