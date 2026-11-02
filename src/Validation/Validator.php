<?php

namespace Omnibridge\Validation;

use Omnibridge\Contract\Validation;
use Omnibridge\Contract\Violation;
use Omnibridge\Validation\Constraint\Choice;
use Omnibridge\Validation\Constraint\Constraint;
use Omnibridge\Validation\Constraint\Count;
use Omnibridge\Validation\Constraint\Email;
use Omnibridge\Validation\Constraint\File;
use Omnibridge\Validation\Constraint\Length;
use Omnibridge\Validation\Constraint\NotBlank;
use Omnibridge\Validation\Constraint\Range;
use Omnibridge\Validation\Constraint\Regex;
use Omnibridge\Validation\Constraint\Unique;

/**
 * Validation in plain PHP, for the kept constraints (Constraint\*): NotBlank,
 * Length, Email, Regex, Choice, Range, Count, File, Unique. An object is
 * validated against the constraints its properties and its class declare as
 * attributes (AttributeConstraints: a validator's own attributes it can read
 * too), a value against the constraints given; a constraint this validator
 * does not know (a host's own) is refused aloud.
 *
 * The messages are Symfony's, their parameters replaced, a plural form chosen
 * by {{ limit }} ("1 character" / "2 characters"); $translate, when given,
 * translates a message template first (Symfony's `validators` catalogue
 * through the host's translations) - fn (string $template, array $parameters): string.
 *
 * Unique asks $exists - fn (string $class, array $criteria, ?object $record): bool,
 * whether another record than $record has those values; without it a Unique
 * constraint is refused aloud.
 */
final class Validator implements Validation
{
    /** @var (\Closure(string, array<string, mixed>, object|null): bool)|null */
    private readonly ?\Closure $exists;

    /** @var (\Closure(string, array<string, mixed>): string)|null */
    private readonly ?\Closure $translate;

    public function __construct(?callable $exists = null, ?callable $translate = null)
    {
        $this->exists = null !== $exists ? $exists(...) : null;
        $this->translate = null !== $translate ? $translate(...) : null;
    }

    public function validate(mixed $value, array $constraints = [], ?array $groups = null): array
    {
        $violations = [];
        if (\is_object($value) && !$value instanceof \DateTimeInterface && !$this->isFile($value)) {
            array_push($violations, ...$this->validateObject($value, $groups));
        }
        array_push($violations, ...$this->only($value, $constraints, $groups));

        return $violations;
    }

    /**
     * The value against the constraints given, nothing of what an object declares: for a host
     * whose validator reads the rest (omnibridge/symfony's, which leaves Unique to this one).
     *
     * @param list<object>      $constraints
     * @param list<string>|null $groups
     *
     * @return list<Violation>
     */
    public function only(mixed $value, array $constraints, ?array $groups = null): array
    {
        $violations = [];
        foreach ($constraints as $constraint) {
            array_push($violations, ...$this->check($value, $constraint, '', $groups, null));
        }

        return $violations;
    }

    /**
     * The Unique constraints a record declares, on its class and its properties.
     *
     * @param list<string>|null $groups
     *
     * @return list<Violation>
     */
    public function uniques(object $record, ?array $groups = null): array
    {
        $violations = [];
        $class = new \ReflectionClass($record);
        foreach ($class->getAttributes(Unique::class) as $attribute) {
            array_push($violations, ...$this->check($record, $attribute->newInstance(), '', $groups, $record));
        }
        foreach ($this->properties($class) as $property) {
            foreach ($property->getAttributes(Unique::class) as $attribute) {
                $value = $property->isInitialized($record) ? $property->getValue($record) : null;
                array_push($violations, ...$this->check($value, $attribute->newInstance(), $property->getName(), $groups, $record));
            }
        }

        return $violations;
    }

    /** @return list<Violation> */
    private function validateObject(object $record, ?array $groups): array
    {
        $violations = [];
        $class = new \ReflectionClass($record);
        foreach ($class->getAttributes(Constraint::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            array_push($violations, ...$this->check($record, $attribute->newInstance(), '', $groups, $record));
        }
        foreach ($this->properties($class) as $property) {
            $constraints = AttributeConstraints::ofProperty($property);
            if ([] === $constraints) {
                continue;
            }
            $value = $property->isInitialized($record) ? $property->getValue($record) : null;
            foreach ($constraints as $constraint) {
                array_push($violations, ...$this->check($value, $constraint, $property->getName(), $groups, $record));
            }
        }

        return $violations;
    }

    /** @return list<\ReflectionProperty> the properties of the class and its parents, each once */
    private function properties(\ReflectionClass $class): array
    {
        $properties = [];
        for ($current = $class; false !== $current; $current = $current->getParentClass()) {
            foreach ($current->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() === $current->getName() && !isset($properties[$property->getName()])) {
                    $properties[$property->getName()] = $property;
                }
            }
        }

        return array_values($properties);
    }

    /** @return list<Violation> */
    private function check(mixed $value, object $constraint, string $path, ?array $groups, ?object $record): array
    {
        if (!$constraint instanceof Constraint) {
            throw new \InvalidArgumentException(sprintf('%s validates the constraints of Omnibridge\Validation\Constraint only, not %s.', self::class, $constraint::class));
        }
        if (!$constraint->appliesTo($groups)) {
            return [];
        }

        return match (true) {
            $constraint instanceof NotBlank => $this->notBlank($value, $constraint, $path),
            $constraint instanceof Length => $this->length($value, $constraint, $path),
            $constraint instanceof Email => $this->email($value, $constraint, $path),
            $constraint instanceof Regex => $this->regex($value, $constraint, $path),
            $constraint instanceof Choice => $this->choice($value, $constraint, $path),
            $constraint instanceof Range => $this->range($value, $constraint, $path),
            $constraint instanceof Count => $this->count($value, $constraint, $path),
            $constraint instanceof File => $this->file($value, $constraint, $path),
            $constraint instanceof Unique => $this->unique($value, $constraint, $path, $record),
            default => throw new \InvalidArgumentException(sprintf('%s does not know the constraint %s.', self::class, $constraint::class)),
        };
    }

    /** @return list<Violation> */
    private function notBlank(mixed $value, NotBlank $constraint, string $path): array
    {
        if (null === $value && $constraint->allowNull) {
            return [];
        }
        // Symfony's rule: false, or empty() but "0".
        $blank = false === $value || (empty($value) && '0' != $value);

        return $blank ? [$this->violation($path, $constraint->message, NotBlank::CODE, ['{{ value }}' => $this->format($value)])] : [];
    }

    /** @return list<Violation> */
    private function length(mixed $value, Length $constraint, string $path): array
    {
        if (null === $value || '' === $value) {
            return [];
        }
        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new \InvalidArgumentException('Length validates a string.');
        }
        $length = mb_strlen((string) $value);
        if (null !== $constraint->max && $length > $constraint->max) {
            return [$this->violation($path, $constraint->maxMessage, Length::TOO_LONG, ['{{ value }}' => $this->format($value), '{{ limit }}' => (string) $constraint->max, '{{ value_length }}' => (string) $length], $constraint->max)];
        }
        if (null !== $constraint->min && $length < $constraint->min) {
            return [$this->violation($path, $constraint->minMessage, Length::TOO_SHORT, ['{{ value }}' => $this->format($value), '{{ limit }}' => (string) $constraint->min, '{{ value_length }}' => (string) $length], $constraint->min)];
        }

        return [];
    }

    /** @return list<Violation> */
    private function email(mixed $value, Email $constraint, string $path): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        return preg_match(Email::PATTERN, (string) $value) ? [] : [$this->violation($path, $constraint->message, Email::CODE, ['{{ value }}' => $this->format($value)])];
    }

    /** @return list<Violation> */
    private function regex(mixed $value, Regex $constraint, string $path): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        return $constraint->match === (bool) preg_match($constraint->pattern, (string) $value) ? [] : [$this->violation($path, $constraint->message, Regex::CODE, ['{{ value }}' => $this->format($value), '{{ pattern }}' => $constraint->pattern])];
    }

    /** @return list<Violation> */
    private function choice(mixed $value, Choice $constraint, string $path): array
    {
        if (null === $value) {
            return [];
        }
        $in = static fn (mixed $item): bool => \in_array($item, $constraint->choices, true);
        if (!$constraint->multiple) {
            return $in($value) ? [] : [$this->violation($path, $constraint->message, Choice::NO_SUCH_CHOICE, ['{{ value }}' => $this->format($value), '{{ choices }}' => $this->formatList($constraint->choices)])];
        }

        $list = \is_array($value) ? $value : iterator_to_array($value);
        foreach ($list as $item) {
            if (!$in($item)) {
                return [$this->violation($path, $constraint->multipleMessage, Choice::NO_SUCH_CHOICE, ['{{ value }}' => $this->format($item), '{{ choices }}' => $this->formatList($constraint->choices)])];
            }
        }
        $count = \count($list);
        if (null !== $constraint->min && $count < $constraint->min) {
            return [$this->violation($path, $constraint->minMessage, Choice::TOO_FEW, ['{{ limit }}' => (string) $constraint->min, '{{ choices }}' => $this->formatList($constraint->choices)], $constraint->min)];
        }
        if (null !== $constraint->max && $count > $constraint->max) {
            return [$this->violation($path, $constraint->maxMessage, Choice::TOO_MANY, ['{{ limit }}' => (string) $constraint->max, '{{ choices }}' => $this->formatList($constraint->choices)], $constraint->max)];
        }

        return [];
    }

    /** @return list<Violation> */
    private function range(mixed $value, Range $constraint, string $path): array
    {
        if (null === $value) {
            return [];
        }
        if (!is_numeric($value) && !$value instanceof \DateTimeInterface) {
            return [$this->violation($path, $constraint->invalidMessage, Range::INVALID, ['{{ value }}' => $this->format($value)])];
        }
        $low = null !== $constraint->min && $value < $constraint->min;
        $high = null !== $constraint->max && $value > $constraint->max;
        if (!$low && !$high) {
            return [];
        }
        $parameters = ['{{ value }}' => $this->format($value)];
        if (null !== $constraint->min && null !== $constraint->max) {
            return [$this->violation($path, $constraint->notInRangeMessage, Range::NOT_IN_RANGE, $parameters + ['{{ min }}' => $this->format($constraint->min), '{{ max }}' => $this->format($constraint->max)])];
        }

        return $low
            ? [$this->violation($path, $constraint->minMessage, Range::TOO_LOW, $parameters + ['{{ limit }}' => $this->format($constraint->min)])]
            : [$this->violation($path, $constraint->maxMessage, Range::TOO_HIGH, $parameters + ['{{ limit }}' => $this->format($constraint->max)])];
    }

    /** @return list<Violation> */
    private function count(mixed $value, Count $constraint, string $path): array
    {
        if (null === $value) {
            return [];
        }
        if (!is_countable($value)) {
            throw new \InvalidArgumentException('Count validates a list.');
        }
        $count = \count($value);
        if (null !== $constraint->max && $count > $constraint->max) {
            return [$this->violation($path, $constraint->maxMessage, Count::TOO_MANY, ['{{ count }}' => (string) $count, '{{ limit }}' => (string) $constraint->max], $constraint->max)];
        }
        if (null !== $constraint->min && $count < $constraint->min) {
            return [$this->violation($path, $constraint->minMessage, Count::TOO_FEW, ['{{ count }}' => (string) $count, '{{ limit }}' => (string) $constraint->min], $constraint->min)];
        }

        return [];
    }

    /** @return list<Violation> */
    private function file(mixed $value, File $constraint, string $path): array
    {
        if (null === $value || '' === $value) {
            return [];
        }
        $file = \is_string($value) ? new \SplFileInfo($value) : $value;
        $size = method_exists($file, 'getSize') ? @$file->getSize() : false;
        if (false === $size || null === $size) {
            return [$this->violation($path, $constraint->notFoundMessage, File::NOT_FOUND, ['{{ file }}' => $this->format($value)])];
        }
        if (null !== $constraint->maxSize && $size > $constraint->maxSize) {
            [$shown, $limit, $suffix] = $this->sizes($size, $constraint->maxSize);

            return [$this->violation($path, $constraint->maxSizeMessage, File::TOO_LARGE, ['{{ size }}' => $shown, '{{ limit }}' => $limit, '{{ suffix }}' => $suffix])];
        }
        if ([] !== $constraint->mimeTypes) {
            $type = match (true) {
                method_exists($file, 'getMimeType') => (string) $file->getMimeType(),
                $file instanceof \SplFileInfo && is_file($file->getPathname()) => (string) (new \finfo(\FILEINFO_MIME_TYPE))->file($file->getPathname()),
                default => '',
            };
            foreach ($constraint->mimeTypes as $allowed) {
                if ($allowed === $type || str_ends_with($allowed, '/*') && str_starts_with($type, substr($allowed, 0, -1))) {
                    return [];
                }
            }

            return [$this->violation($path, $constraint->mimeTypesMessage, File::INVALID_MIME_TYPE, ['{{ type }}' => '"'.$type.'"', '{{ types }}' => $this->formatList($constraint->mimeTypes)])];
        }

        return [];
    }

    /** @return list<Violation> */
    private function unique(mixed $value, Unique $constraint, string $path, ?object $record): array
    {
        if (null === $this->exists) {
            throw new \LogicException(sprintf('%s checks Unique with a lookup of the records ($exists) only.', self::class));
        }
        if (\is_array($value)) {
            // On the values a form holds, by field: its class named, nothing kept yet.
            $criteria = array_intersect_key($value, array_flip($constraint->fields));
            $class = $constraint->entityClass;
        } elseif ('' !== $path) {
            // On a property: that field, the record its holder.
            $fields = [] !== $constraint->fields ? $constraint->fields : [$path];
            $criteria = array_fill_keys($fields, $value);
            $class = $constraint->entityClass ?? (null !== $record ? $record::class : null);
        } else {
            if (!\is_object($value)) {
                throw new \InvalidArgumentException('Unique on a class validates a record.');
            }
            $criteria = [];
            foreach ($constraint->fields as $field) {
                $property = new \ReflectionProperty($value, $field);
                $criteria[$field] = $property->isInitialized($value) ? $property->getValue($value) : null;
            }
            $class = $constraint->entityClass ?? $value::class;
            $record = $value;
        }
        if (null === $class || \in_array(null, $criteria, true)) {
            return [];
        }
        if (!($this->exists)($class, $criteria, $record)) {
            return [];
        }
        $at = $constraint->errorPath ?? ('' !== $path ? $path : ($constraint->fields[0] ?? ''));

        return [$this->violation($at, $constraint->message, Unique::CODE, ['{{ value }}' => $this->format(reset($criteria))])];
    }

    /**
     * @param array<string, string> $parameters
     */
    private function violation(string $path, string $template, string $code, array $parameters, ?int $plural = null): Violation
    {
        if (null !== $this->translate) {
            $message = ($this->translate)($template, $parameters + (null !== $plural ? ['%count%' => $plural] : []));
        } else {
            $forms = explode('|', $template);
            $message = strtr(1 === $plural || 1 === \count($forms) ? $forms[0] : $forms[1], $parameters);
        }

        return new Violation($path, $message, $code, $parameters);
    }

    private function isFile(object $value): bool
    {
        return $value instanceof \SplFileInfo;
    }

    private function format(mixed $value): string
    {
        return match (true) {
            null === $value => 'null',
            \is_bool($value) => $value ? 'true' : 'false',
            \is_string($value) => '"'.$value.'"',
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            \is_scalar($value) => (string) $value,
            \is_array($value) => 'array',
            $value instanceof \BackedEnum => '"'.$value->value.'"',
            default => 'object',
        };
    }

    /** @param list<mixed> $list */
    private function formatList(array $list): string
    {
        return implode(', ', array_map($this->format(...), $list));
    }

    /** @return array{0: string, 1: string, 2: string} Symfony's binary-free sizes: the size, the limit and their suffix */
    private function sizes(int $size, int $limit): array
    {
        $coef = 1000;
        foreach (['bytes' => 1, 'kB' => $coef, 'MB' => $coef ** 2] as $suffix => $factor) {
            if ($limit < $factor * $coef || 'MB' === $suffix) {
                $round = static fn (float $n): string => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

                return [$round($size / $factor), $round($limit / $factor), $suffix];
            }
        }

        return [(string) $size, (string) $limit, 'bytes'];
    }
}
