<?php

namespace Omnibridge\Validation\Constraint;

/**
 * One of the kept constraints (plan "omnibase agnostic", step F5b): a plain
 * object, an attribute on a property (or on a class: Unique), that every
 * host's Validation reads - symfony/validator through its own constraints
 * (omnibridge/symfony), Laravel's validator through its rules
 * (omnibridge/laravel), Omnibridge\Validation\Validator in plain PHP.
 *
 * Its messages are Symfony's own, so a form says the same on every host;
 * the host translates them (Symfony's `validators` catalogue).
 */
abstract class Constraint
{
    public const DEFAULT_GROUP = 'Default';

    /** @var list<string> */
    public readonly array $groups;

    /** @param list<string>|null $groups */
    public function __construct(?array $groups = null)
    {
        $this->groups = $groups ?? [self::DEFAULT_GROUP];
    }

    /** @param list<string>|null $groups the groups validated, Default when null */
    public function appliesTo(?array $groups): bool
    {
        return [] !== array_intersect($groups ?? [self::DEFAULT_GROUP], $this->groups);
    }
}
