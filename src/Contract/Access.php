<?php

namespace Omnibridge\Contract;

/**
 * What the visitor may do: an attribute (a role, "ROLE_ADMIN", or a
 * permission a policy answers, "edit" on a subject) granted or not, and who
 * the visitor is.
 *
 * Generic: Symfony's authorization checker and token storage, Laravel's gate
 * and guard answer the same two questions.
 *
 * Formerly Base\Contract\Access, which remains its alias until omnibase 4's
 * final pass.
 */
interface Access
{
    /** Whether the visitor holds the attribute, on the subject when one is given; false for nobody signed in unless the attribute is granted to anyone. */
    public function isGranted(mixed $attribute, mixed $subject = null): bool;

    /** The visitor signed in (an application's user), null for nobody. */
    public function getUser(): ?object;
}
