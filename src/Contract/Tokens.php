<?php

namespace Omnibridge\Contract;

/**
 * The token a form carries for an intent, against cross-site requests: what a
 * page's POST sends back and its action checks.
 *
 * Generic: Symfony's CSRF token manager (one token per intent), Laravel's
 * session token (one for every intent). A token made by one host is checked
 * by that host only.
 *
 * Formerly Base\Contract\Tokens, which remains its alias until omnibase 4's
 * final pass.
 */
interface Tokens
{
    /** The token of that intent ("profile_edit", "delete_12"...) for the visitor's session. */
    public function csrf(string $intent): string;
}
