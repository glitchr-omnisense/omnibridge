<?php

namespace Omnibridge\Schema\Declaration;

/**
 * What an entity class declares of its mapping, read from its attributes and
 * nothing else: the class's own attributes (Entity, Table, InheritanceType...),
 * then each property in the order reflection lists them - the inherited ones
 * among them, with the class that declares each - and each public method
 * carrying a lifecycle attribute. Nothing is resolved here (no default name,
 * no type guessed): that is the description's (Omnibridge\Schema\Description),
 * and a persistence's that reads the declaration as its mapping.
 */
final class ClassDeclaration
{
    /**
     * @param list<Attribute> $attributes the class's own, in the order they are written
     * @param list<Member>    $properties in reflection's order
     * @param list<Member>    $methods    the public ones that carry a mapping attribute, in reflection's order
     */
    public function __construct(
        public readonly string $class,
        public readonly array $attributes = [],
        public readonly array $properties = [],
        public readonly array $methods = [],
    ) {
    }

    /** Entity, MappedSuperclass or Embeddable: what the class is, null for a class the mapping does not hold. */
    public function kind(): ?string
    {
        foreach (['Entity', 'MappedSuperclass', 'Embeddable'] as $kind) {
            if (null !== $this->attribute($kind)) {
                return $kind;
            }
        }

        return null;
    }

    /** The class attribute of that name (the last one written), null if none. */
    public function attribute(string $name): ?Attribute
    {
        return (new Member($this->class, $this->class, $this->attributes))->attribute($name);
    }

    /** @return list<Attribute> */
    public function attributes(string $name): array
    {
        return (new Member($this->class, $this->class, $this->attributes))->attributes($name);
    }

    public function property(string $name): ?Member
    {
        foreach ($this->properties as $property) {
            if ($property->name === $name) {
                return $property;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $export = static fn (array $items) => array_map(static fn (Attribute|Member $item) => $item->toArray(), $items);

        return array_filter([
            'class' => $this->class,
            'attributes' => $export($this->attributes),
            'properties' => $export($this->properties),
            'methods' => $export($this->methods),
        ], static fn ($value) => [] !== $value);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['class'],
            array_map(Attribute::fromArray(...), $data['attributes'] ?? []),
            array_map(Member::fromArray(...), $data['properties'] ?? []),
            array_map(Member::fromArray(...), $data['methods'] ?? []),
        );
    }
}
