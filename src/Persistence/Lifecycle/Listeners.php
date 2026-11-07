<?php

namespace Omnibridge\Persistence\Lifecycle;

/**
 * The listeners of a persistence's Lifecycle events that a package declares to every host (omnisense/omnibase's plan
 * "omnibase agnostic", step B2): a service tagged TAG is a callable `(object $event): void`, handed every event the
 * persistence chosen dispatches (PrePersist ... PostFlush, PostLoad) after omnibase's own behaviours. Each host
 * compiles the tag - omnibridge/symfony from its container's tagged services, omnibridge/laravel from
 * `$app->tagged(TAG)` - into the persistence's `listeners`.
 *
 * A package that indexes, audits or mirrors writes listens here once and follows the writes of whichever persistence
 * dispatches these events (omnibridge/eloquent); on Doctrine, which dispatches its own, the package keeps its Doctrine
 * listener (glitchr/typesense-bundle: its indexer on both). A listener ignores the events it has no use for.
 */
final class Listeners
{
    public const TAG = 'omnibridge.lifecycle_listener';

    /**
     * One callable for a unit of work's listen(): the listeners resolved at the first event (a closure of them or a
     * lazy iterable, so that none is made before a write needs it), then called in their order.
     *
     * @param iterable<callable(object): void>|\Closure(): iterable<callable(object): void> $listeners
     *
     * @return \Closure(object): void
     */
    public static function chain(iterable|\Closure $listeners): \Closure
    {
        $resolved = null;

        return static function (object $event) use (&$resolved, $listeners): void {
            if (null === $resolved) {
                $resolved = [];
                foreach ($listeners instanceof \Closure ? $listeners() : $listeners as $listener) {
                    if (!\is_callable($listener)) {
                        throw new \InvalidArgumentException(sprintf('A lifecycle listener (tagged "%s") is a %s, not a callable.', self::TAG, get_debug_type($listener)));
                    }
                    $resolved[] = $listener;
                }
            }
            foreach ($resolved as $listener) {
                $listener($event);
            }
        };
    }
}
