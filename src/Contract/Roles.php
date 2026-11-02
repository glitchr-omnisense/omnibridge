<?php

namespace Omnibridge\Contract;

/**
 * The roles some roles reach: themselves and every role below them in the
 * host's hierarchy ("ROLE_ADMIN" reaching "ROLE_STAFF" and "ROLE_USER").
 *
 * Generic: Symfony's role hierarchy (security.yaml's role_hierarchy),
 * Laravel's config/omniauth.php's role_hierarchy. A host without a hierarchy
 * answers the roles given.
 */
interface Roles
{
    /**
     * @param list<string> $roles
     *
     * @return list<string> the roles given and those they reach
     */
    public function reachable(array $roles): array;
}
