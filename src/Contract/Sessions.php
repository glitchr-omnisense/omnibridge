<?php

namespace Omnibridge\Contract;

/**
 * The visitor's session, as the application ends it: a user signed out when
 * that user is the one signed in (the session forgotten, a remember-me cookie
 * cleared), and told so; otherwise nothing here.
 *
 * Generic: Symfony's token storage, Laravel's guard logout().
 *
 * Formerly Base\Contract\Sessions, an alias removed at omnibase 4's
 * final pass.
 */
interface Sessions
{
    /** Signs the user out if it is the visitor signed in here; false when it is not (nobody, or someone else). */
    public function signOut(object $user): bool;
}
