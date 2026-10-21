<?php

namespace Omnibridge\Schema;

/**
 * How a hierarchy of entities is stored: one table for all (single_table) or
 * one per class joined on the identifier (joined); the column that tells a
 * row's class, the value of each class in it, and the value of this one.
 */
final class Inheritance
{
    public const SINGLE_TABLE = 'single_table';
    public const JOINED = 'joined';

    use Exports;

    /** @param array<string, string> $map discriminator value => class */
    public function __construct(
        public readonly string $type,
        public readonly Column $column,
        public readonly array $map = [],
        public readonly ?string $value = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $data['column'] = Column::fromArray($data['column']);

        return new self(...$data);
    }
}
