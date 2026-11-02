<?php

namespace Omnibridge\Validation\Constraint;

/** How many items a list holds, at least and at most. */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class Count extends Constraint
{
    public const TOO_FEW = 'bef8e338-6ae5-4caf-b8e2-50e7b0579e69';
    public const TOO_MANY = '756b1212-697c-468d-a9ad-50dd783bb169';

    /** @param list<string>|null $groups */
    public function __construct(
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly string $minMessage = 'This collection should contain {{ limit }} element or more.|This collection should contain {{ limit }} elements or more.',
        public readonly string $maxMessage = 'This collection should contain {{ limit }} element or less.|This collection should contain {{ limit }} elements or less.',
        ?array $groups = null,
    ) {
        parent::__construct($groups);
    }
}
