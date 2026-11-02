<?php

namespace Omnibridge\Validation\Constraint;

/**
 * No other record of the class has the same values for these fields: on a
 * class (the record's own fields) or on a property (that one field). The
 * lookup is the application's persistence (Omnibridge\Validation\Validator's
 * $exists; omnibase gives its repositories).
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class Unique extends Constraint
{
    public const CODE = '23bd9dbf-6b9b-41cd-a99e-4844bcf3077f';

    /**
     * @param list<string>      $fields the fields compared, the property's own when on a property
     * @param string|null       $entityClass the class looked into, the record's own when null
     * @param list<string>|null $groups
     */
    public function __construct(
        public readonly array $fields = [],
        public readonly ?string $entityClass = null,
        public readonly ?string $errorPath = null,
        public readonly string $message = 'This value is already used.',
        ?array $groups = null,
    ) {
        parent::__construct($groups);
    }
}
