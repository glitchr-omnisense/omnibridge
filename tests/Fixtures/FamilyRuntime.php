<?php

namespace Omnibridge\Tests\Fixtures;

use Omnibridge\Runtime;

/** An application family's registry over omnibridge's (as Base\Contract\Runtime): its own accessors, the same state. */
final class FamilyRuntime extends Runtime
{
    public static function link(string $name): string
    {
        return self::urls()->path($name);
    }
}
