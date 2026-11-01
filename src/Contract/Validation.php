<?php

namespace Omnibridge\Contract;

/**
 * A value checked against constraints: what a form's processing, an import or
 * an action asks before it keeps anything. The constraints are those the
 * application declares (attributes on its entities and DTOs, or objects given);
 * the host's validator reads them.
 *
 * Generic: symfony/validator (omnibridge/symfony), Laravel's validator
 * (omnibridge/laravel, the constraints translated into its rules), a small
 * validator of the kept constraints for plain PHP.
 */
interface Validation
{
    /**
     * The violations of the value: an object against the constraints it
     * declares, plus those given; a scalar or an array against those given.
     * Empty when the value is valid.
     *
     * @param list<object>      $constraints
     * @param list<string>|null $groups      the validation groups, the host's default when null
     *
     * @return list<Violation>
     */
    public function validate(mixed $value, array $constraints = [], ?array $groups = null): array;
}
