<?php

namespace Omnibridge\Validation;

use Omnibridge\Validation\Constraint\Choice;
use Omnibridge\Validation\Constraint\Constraint;
use Omnibridge\Validation\Constraint\Count;
use Omnibridge\Validation\Constraint\Email;
use Omnibridge\Validation\Constraint\Length;
use Omnibridge\Validation\Constraint\NotBlank;
use Omnibridge\Validation\Constraint\Range;
use Omnibridge\Validation\Constraint\Regex;
use Omnibridge\Validation\Constraint\Unique;

/**
 * The rules a class declares, read as kept constraints whichever attributes
 * name them: omnibridge's own, or a validator's by its attribute's name
 * (symfony/validator's, an application's registered with register()). The
 * attribute is read by reflection, never instantiated: its class need not be
 * installed, so one rule stays on one line of a model or an entity, and every
 * host's Validation answers it. An attribute no reader knows is left alone:
 * a rule of one host (its own validator reads it) or nobody's.
 *
 * Symfony's constraints take their first argument as a default option or by
 * name (`new Count(min: 1)`); a reader gets the arguments as written.
 */
final class AttributeConstraints
{
    /** @var array<string, \Closure(array<int|string, mixed>): ?Constraint> */
    private static array $readers = [];

    private static bool $defaults = false;

    private function __construct()
    {
    }

    /** @param callable(array<int|string, mixed>): ?Constraint $reader the attribute's arguments => its kept constraint */
    public static function register(string $attribute, callable $reader): void
    {
        self::$readers[$attribute] = $reader(...);
    }

    /**
     * What a property declares.
     *
     * @return list<Constraint>
     */
    public static function ofProperty(\ReflectionProperty $property): array
    {
        return self::read($property->getAttributes());
    }

    /**
     * What a class itself declares (its uniqueness), its parents' first; not its properties'.
     *
     * @param class-string $class
     *
     * @return list<Constraint>
     */
    public static function ofClass(string $class): array
    {
        $constraints = [];
        for ($current = new \ReflectionClass($class); false !== $current; $current = $current->getParentClass()) {
            $constraints = [...self::read($current->getAttributes()), ...$constraints];
        }

        return $constraints;
    }

    /**
     * @param list<\ReflectionAttribute<object>> $attributes
     *
     * @return list<Constraint>
     */
    private static function read(array $attributes): array
    {
        self::defaults();
        $constraints = [];
        foreach ($attributes as $attribute) {
            $name = $attribute->getName();
            if (is_subclass_of($name, Constraint::class)) {
                $constraints[] = $attribute->newInstance();
            } elseif (null !== $reader = self::$readers[$name] ?? null) {
                if (null !== $constraint = $reader($attribute->getArguments())) {
                    $constraints[] = $constraint;
                }
            }
        }

        return $constraints;
    }

    private static function defaults(): void
    {
        if (self::$defaults) {
            return;
        }
        self::$defaults = true;
        $symfony = 'Symfony\Component\Validator\Constraints\\';
        self::$readers += [
            $symfony.'NotBlank' => self::keep(NotBlank::class, ['message', 'allowNull', 'groups']),
            $symfony.'Length' => self::keep(Length::class, ['min', 'max', 'minMessage', 'maxMessage', 'groups'], 'max'),
            $symfony.'Email' => self::keep(Email::class, ['message', 'groups']),
            $symfony.'Regex' => self::keep(Regex::class, ['pattern', 'message', 'match', 'groups'], 'pattern'),
            $symfony.'Choice' => self::keep(Choice::class, ['choices', 'multiple', 'min', 'max', 'message', 'multipleMessage', 'minMessage', 'maxMessage', 'groups'], 'choices'),
            $symfony.'Range' => self::keep(Range::class, ['min', 'max', 'notInRangeMessage', 'minMessage', 'maxMessage', 'invalidMessage', 'groups']),
            $symfony.'Count' => self::keep(Count::class, ['min', 'max', 'minMessage', 'maxMessage', 'groups']),
            'Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity' => static fn (array $arguments): Constraint => self::unique($arguments),
        ];
    }

    /**
     * A reader of the arguments the kept constraint has too, the others left out.
     *
     * @param class-string<Constraint> $class
     * @param list<string>             $names
     */
    public static function keep(string $class, array $names, ?string $default = null): \Closure
    {
        return static function (array $arguments) use ($class, $names, $default): Constraint {
            if (null !== $default && \array_key_exists(0, $arguments)) {
                $arguments[$default] = $arguments[0];
            }

            return new $class(...array_intersect_key($arguments, array_flip($names)));
        };
    }

    /** A UniqueEntity's arguments as Unique: fields given as one string or a list. */
    public static function unique(array $arguments, ?string $defaultMessage = null): Unique
    {
        $message = $arguments['message'] ?? $defaultMessage;

        return new Unique(
            fields: (array) ($arguments['fields'] ?? $arguments[0] ?? []),
            entityClass: $arguments['entityClass'] ?? null,
            errorPath: $arguments['errorPath'] ?? null,
            message: $message ?? 'This value is already used.',
            groups: $arguments['groups'] ?? null,
        );
    }
}
