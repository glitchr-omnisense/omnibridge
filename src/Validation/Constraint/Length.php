<?php

namespace Omnibridge\Validation\Constraint;

/** A string's length in characters, between min and max (either optional). Null and "" pass. */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Length extends Constraint
{
    public const TOO_SHORT = '9ff3fdc4-b214-49db-8718-39c315e33d45';
    public const TOO_LONG = 'd94b19cc-114f-4f44-9cc4-4138e80a87b9';

    /** @param list<string>|null $groups */
    public function __construct(
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly string $minMessage = 'This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.',
        public readonly string $maxMessage = 'This value is too long. It should have {{ limit }} character or less.|This value is too long. It should have {{ limit }} characters or less.',
        ?array $groups = null,
    ) {
        if (null === $min && null === $max) {
            throw new \InvalidArgumentException('Length needs a min or a max.');
        }
        parent::__construct($groups);
    }
}
