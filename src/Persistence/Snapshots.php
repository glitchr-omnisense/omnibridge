<?php

namespace Omnibridge\Persistence;

/**
 * What a unit of work remembers of each entity it manages: the values it held
 * when it was read or last flushed, which an application compares with what it
 * holds now (a user approved since, a state that changed). A backend whose
 * UnitOfWork keeps such snapshots implements this beside it (Doctrine's
 * original entity data, omnibridge/doctrine; Eloquent's original attributes,
 * omnibridge/eloquent).
 */
interface Snapshots
{
    /**
     * The entity's values as read or last flushed, by property: its fields and
     * its to-one associations (the associated entity); [] for an entity the
     * unit of work does not manage or never wrote.
     *
     * @return array<string, mixed>
     */
    public function original(object $entity): array;
}
