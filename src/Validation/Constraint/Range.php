<?php

namespace Omnibridge\Validation\Constraint;

/** A number (or a date) between min and max, either optional. Null passes. */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Range extends Constraint
{
    public const NOT_IN_RANGE = '04b91c99-a946-4221-afc5-e65ebac401eb';
    public const TOO_HIGH = '2d28afcb-e32e-45fb-a815-01c431a86a69';
    public const TOO_LOW = '76454e69-502c-46c5-9643-f447d837c4d5';
    public const INVALID = 'ad9a9798-7a99-4df7-8ce9-46e416a1e60b';

    /** @param list<string>|null $groups */
    public function __construct(
        public readonly int|float|\DateTimeInterface|null $min = null,
        public readonly int|float|\DateTimeInterface|null $max = null,
        public readonly string $notInRangeMessage = 'This value should be between {{ min }} and {{ max }}.',
        public readonly string $minMessage = 'This value should be {{ limit }} or more.',
        public readonly string $maxMessage = 'This value should be {{ limit }} or less.',
        public readonly string $invalidMessage = 'This value should be a valid number.',
        ?array $groups = null,
    ) {
        if (null === $min && null === $max) {
            throw new \InvalidArgumentException('Range needs a min or a max.');
        }
        parent::__construct($groups);
    }
}
