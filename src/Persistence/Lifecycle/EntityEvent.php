<?php

namespace Omnibridge\Persistence\Lifecycle;

use Omnibridge\Persistence\UnitOfWork;

/**
 * A moment of an entity's life in its unit of work, dispatched by the backend
 * through the host's PSR-14 dispatcher, at the moments Doctrine dispatches its
 * own (omnibridge/doctrine relays Doctrine's; omnibridge/eloquent dispatches them
 * in its flush): an application's behaviours (a slug, a timestamp, an upload)
 * listen to these, whichever backend runs.
 */
abstract class EntityEvent
{
    public function __construct(
        public readonly object $entity,
        public readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** The entity's changes computed again, after a listener changed it. */
    public function recompute(): void
    {
        $this->unitOfWork->recompute($this->entity);
    }
}
