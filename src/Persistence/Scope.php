<?php

namespace Omnibridge\Persistence;

/**
 * A condition every query of some entities carries while it is enabled: the
 * trashed rows left out, the sealed ones hidden, a tenant's rows only. The
 * backend adds it to its queries (Doctrine's SQL filters, Eloquent's global
 * scopes); the application enables and disables it by name (Backend).
 */
interface Scope
{
    /** The scope's name, by which it is enabled and disabled ("trash", "vault"). */
    public function name(): string;

    /** Whether the scope applies to the entities of that class. */
    public function appliesTo(string $class): bool;

    /**
     * The condition, as criteria on the entity's fields: field => value (a list
     * for IN, null for IS NULL), combined with And.
     *
     * @param array<string, mixed> $parameters what the scope was enabled with
     *
     * @return array<string, mixed>
     */
    public function criteria(string $class, array $parameters = []): array;
}
