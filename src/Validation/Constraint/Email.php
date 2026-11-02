<?php

namespace Omnibridge\Validation\Constraint;

/** An e-mail address (the HTML5 rule, Symfony's default mode). Null and "" pass. */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Email extends Constraint
{
    public const CODE = 'bd79c0ab-ddba-46cc-a703-a7a4b08de310';

    /** Symfony's Email::VALIDATION_MODE_HTML5 pattern. */
    public const PATTERN = '/^[a-zA-Z0-9.!#$%&\'*+\\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/D';

    /** @param list<string>|null $groups */
    public function __construct(
        public readonly string $message = 'This value is not a valid email address.',
        ?array $groups = null,
    ) {
        parent::__construct($groups);
    }
}
