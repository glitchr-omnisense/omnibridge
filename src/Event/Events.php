<?php

namespace Omnibridge\Event;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * An event dispatched through any PSR-14 dispatcher, by its name where the
 * dispatcher listens by name: Symfony's takes the name as its second argument
 * (symfony/event-dispatcher-contracts' signature, which extends PSR-14's);
 * any other is given the event alone, a NamedEvent carrying its name.
 */
final class Events
{
    /**
     * @template T of object
     *
     * @param T $event
     *
     * @return T
     */
    public static function dispatch(EventDispatcherInterface $dispatcher, object $event, ?string $name = null): object
    {
        $name ??= $event instanceof NamedEvent ? $event->eventName() : null;
        if (null !== $name && (new \ReflectionMethod($dispatcher, 'dispatch'))->getNumberOfParameters() > 1) {
            return $dispatcher->dispatch($event, $name);
        }

        return $dispatcher->dispatch($event);
    }
}
