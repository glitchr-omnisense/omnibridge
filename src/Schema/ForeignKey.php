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

    /**
     * A referential action (ON DELETE, ON UPDATE) as Doctrine DBAL keeps it in its ForeignKeyConstraint: in upper
     * case, and null for RESTRICT and NO ACTION, which it reads as no action at all (the option dropped).
     */
    public static function action(?string $action): ?string
    {
        return null === $action || \in_array(strtoupper($action), ['RESTRICT', 'NO ACTION'], true) ? null : strtoupper($action);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
