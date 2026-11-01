<?php

namespace Omnibridge\Persistence;

/**
 * The entities an application keeps, whatever keeps them: what its code asks
 * where it would otherwise speak to an ORM's entity manager. The entities are
 * the application's plain PHP objects; the backend maps them to its tables
 * (Omnibridge\Schema describes the tables once, for every backend).
 *
 * Generic: Doctrine's entity manager (omnibridge/doctrine), Eloquent's
 * connection with an identity map and change tracking (omnibridge/eloquent).
 * Under one host or none: the backend is installed, not the framework.
 */
interface UnitOfWork
{
    /** The entity kept at the next flush (inserted when it is new). */
    public function persist(object $entity): void;

    /** The entity deleted at the next flush. */
    public function remove(object $entity): void;

    /** Writes what was persisted, changed or removed since the last flush, the Lifecycle events dispatched on the way. */
    public function flush(): void;

    /** The entity read again from the database, its unsaved changes lost. */
    public function refresh(object $entity): void;

    /** Whether the unit of work manages that entity (read through it or persisted, not removed). */
    public function contains(object $entity): bool;

    /**
     * The entity of that class and identifier, without reading it until one of
     * its fields is asked (a reference to set an association with).
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function reference(string $class, mixed $id): object;

    /**
     * Runs the work in a transaction, flushed and committed when it returns,
     * rolled back when it throws (the exception thrown again).
     *
     * @template R
     *
     * @param callable(self): R $work
     *
     * @return R
     */
    public function transactional(callable $work): mixed;

    /** Whether the entity was never written (it has no row yet). */
    public function isNew(object $entity): bool;

    /** The entity's identifier: its value, or field => value for a composite one; null for a new entity whose identifier the database gives. */
    public function identifier(object $entity): mixed;

    /** The entity's class, the backend's proxy or lazy object seen through. */
    public function className(object $entity): string;

    /**
     * What changed in the entity since it was read or last flushed.
     *
     * @return array<string, array{0: mixed, 1: mixed}> field => [before, after]
     */
    public function changes(object $entity): array;

    /** The entity's changes computed again: for a listener that changed it during a flush (OnFlush, PreUpdate). */
    public function recompute(object $entity): void;
}
