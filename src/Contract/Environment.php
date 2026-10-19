<?php

namespace Omnibridge\Contract;

/**
 * What the application runs as: its environment's name ("prod", "dev",
 * "test"), whether it runs in debug, and its secret (the salt of what it
 * hashes).
 *
 * Generic: Symfony's kernel.environment, kernel.debug and kernel.secret;
 * Laravel's APP_ENV, app.debug and app.key.
 *
 * Formerly Base\Contract\Environment, which remains its alias until omnibase
 * 4's final pass.
 */
interface Environment
{
    /** The environment's name: "prod", "dev", "test"... */
    public function name(): string;

    public function isDebug(): bool;

    /** The application's secret, which salts what it hashes. */
    public function secret(): string;
}
