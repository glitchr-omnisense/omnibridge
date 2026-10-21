<?php

namespace Omnibridge\Schema\Declaration;

/** A property or a method of an entity class with the mapping attributes it carries, and the class that declares it. */
final class Member
{
    /** @param list<Attribute> $attributes in the order they are written */
    public function __construct(
        public readonly string $name,
        public readonly string $class,
        public readonly array $attributes = [],
    ) {
    }

    /** The attribute of that name (the last one written, as a non-repeatable attribute is read), null if none. */
    public function attribute(string $name): ?Attribute
    {
        $found = null;
        foreach ($this->attributes as $attribute) {
            if ($attribute->name === $name) {
                $found = $attribute;
            }
        }

        return $found;
    }

    /** @return list<Attribute> every attribute of that name (a repeatable one), in order */
    public function attributes(string $name): array
    {
        return array_values(array_filter($this->attributes, static fn (Attribute $attribute) => $attribute->name === $name));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['name' => $this->name, 'class' => $this->class] + ($this->attributes ? ['attributes' => array_map(static fn (Attribute $attribute) => $attribute->toArray(), $this->attributes)] : []);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['name'], $data['class'], array_map(Attribute::fromArray(...), $data['attributes'] ?? []));
    }
}
