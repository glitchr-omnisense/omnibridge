<?php

namespace Omnibridge\Validation\Constraint;

/** Not null (unless $allowNull), not "", not an empty array, not false. */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class NotBlank extends Constraint
{
    public const CODE = 'c1051bb4-d103-4f74-8988-acbcafc7fdc3';

    /** @param list<string>|null $groups */
    public function __construct(
        public readonly string $message = 'This value should not be blank.',
        public readonly bool $allowNull = false,
        ?array $groups = null,
    ) {
        parent::__construct($groups);
    }
}
