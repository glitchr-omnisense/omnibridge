<?php

namespace Omnibridge\Persistence;

/**
 * What a unit of work is about to write, read during a flush (an OnFlush listener: a reference numbered when an
 * order is inserted, an invoice posted to a ledger, a revision recorded): the entities scheduled for insertion,
 * update and deletion, and the to-many collections whose members changed. Each persistence's unit of work answers it
 * beside UnitOfWork (`$event->unitOfWork instanceof Schedule`).
 */
interface Schedule
{
    /** @return list<object> */
    public function scheduledInsertions(): array;

    /** @return list<object> */
    public function scheduledUpdates(): array;

    /** @return list<object> */
    public function scheduledDeletions(): array;

    /**
     * The collections whose members this flush adds or removes: the owning entity, the association's name, the
     * members added and the members removed.
     *
     * @return list<array{entity: object, field: string, added: list<object>, removed: list<object>}>
     */
    public function scheduledCollectionUpdates(): array;
}
