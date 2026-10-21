<?php

namespace Omnibridge\Schema;

/** A foreign key of a table: its columns, the table and columns they reference, what a deletion there does. */
final class ForeignKey
{
    use Exports;

    /**
     * @param list<string> $columns
     * @param list<string> $referencedColumns
     */
    public function __construct(
        public readonly string $name,
        public readonly array $columns,
        public readonly string $table,
        public readonly array $referencedColumns,
        public readonly ?string $onDelete = null,
        public readonly ?string $onUpdate = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
