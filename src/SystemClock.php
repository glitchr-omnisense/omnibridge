<?php

namespace Omnibridge;

use Psr\Clock\ClockInterface;

/**
 * The clock of the machine, the registry's clock when no host gave one
 * (Runtime::clock()). A host gives its own, which a test can freeze
 * (symfony/clock's, Laravel's dates).
 *
 * Formerly Base\Contract\SystemClock, an alias removed at omnibase 4's
 * final pass.
 */
final class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
