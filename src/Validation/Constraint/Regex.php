<?php

namespace Omnibridge\Validation\Constraint;

/** A string matching the pattern (or not matching it, $match false). Null and "" pass. */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Regex extends Constraint
{
    public const CODE = 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3';

    /** @param list<string>|null $groups */
    public function __construct(
        public readonly string $pattern,
        public readonly bool $match = true,
        public readonly string $message = 'This value is not valid.',
        ?array $groups = null,
    ) {
        parent::__construct($groups);
    }
}
