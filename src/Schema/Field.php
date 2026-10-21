<?php

namespace Omnibridge\Schema;

/**
 * A property of an entity kept in a column of its table: the column's name,
 * its portable type, what the type leaves open, whether it is (part of) the
 * identifier and how the identifier is generated, the PHP enumeration its
 * values are read into; where it comes from - the entity it is inherited from
 * (null: the entity's own, or a mapped superclass's or a trait's) and the
 * class that declares the property.
 *
 * @phpstan-type Options array<string, mixed>
 */
final class Field
{
    use Exports;

    /** @param array<string, mixed> $options what else the column says (default, unsigned, fixed, comment, charset...) */
    public function __construct(
        public readonly string $property,
        public readonly string $column,
        public readonly string $type,
        public readonly bool $nullable = false,
        public readonly ?int $length = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
        public readonly bool $unique = false,
        public readonly array $options = [],
        public readonly bool $id = false,
        public readonly ?string $generated = null,
        public readonly ?string $enum = null,
        public readonly ?string $inheritedFrom = null,
        public readonly ?string $declaredIn = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
