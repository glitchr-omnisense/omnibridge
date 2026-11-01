<?php

namespace Omnibridge\Persistence;

/**
 * The persistence installed: its name, what it can do, its unit of work and its
 * scopes. omnibridge/doctrine and omnibridge/eloquent each answer it, so that an
 * application swaps one for the other by what it installs (and, with both
 * installed, by its host's configuration).
 */
interface Backend
{
    /** A second-level cache of entities between requests. */
    public const SECOND_LEVEL_CACHE = 'second_level_cache';

    /** A cache of query results. */
    public const RESULT_CACHE = 'result_cache';

    /** Associations read only when they are asked (proxies, lazy objects). */
    public const LAZY_ASSOCIATIONS = 'lazy_associations';

    /** Transactions (UnitOfWork::transactional()). */
    public const TRANSACTIONS = 'transactions';

    /** The backend's name: "doctrine", "eloquent". */
    public function name(): string;

    /** Whether the backend can do that (one of the constants, or a name of its own). */
    public function supports(string $feature): bool;

    public function unitOfWork(): UnitOfWork;

    /**
     * Adds the scope to every query of the entities it applies to, until it is disabled.
     *
     * @param array<string, mixed> $parameters
     */
    public function enableScope(string $name, array $parameters = []): void;

    public function disableScope(string $name): void;

    public function isScopeEnabled(string $name): bool;
}
