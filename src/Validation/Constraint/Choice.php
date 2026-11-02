<?php

namespace Omnibridge\Validation\Constraint;

/** One of the choices (or, $multiple, a list of them, between min and max). Null passes. */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Choice extends Constraint
{
    public const NO_SUCH_CHOICE = '8e179f1b-97aa-4560-a02f-2a8b42e49df7';
    public const TOO_FEW = '11edd7eb-5872-4b6e-9f12-89923999fd0e';
    public const TOO_MANY = '9bd98e49-211c-433f-8630-fd1c2d0f08c3';

    /**
     * @param list<mixed>       $choices
     * @param list<string>|null $groups
     */
    public function __construct(
        public readonly array $choices,
        public readonly bool $multiple = false,
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly string $message = 'The value you selected is not a valid choice.',
        public readonly string $multipleMessage = 'One or more of the given values is invalid.',
        public readonly string $minMessage = 'You must select at least {{ limit }} choice.|You must select at least {{ limit }} choices.',
        public readonly string $maxMessage = 'You must select at most {{ limit }} choice.|You must select at most {{ limit }} choices.',
        ?array $groups = null,
    ) {
        parent::__construct($groups);
    }
}
