<?php

namespace Omnibridge\Schema;

/**
 * A table: its columns in the order the database holds them, its primary key,
 * its indexes and unique indexes, its foreign keys, by name.
 */
final class Table
{
    use Exports;

    /**
     * @param array<string, Column>     $columns     by name
     * @param list<string>              $primaryKey  the columns
     * @param array<string, Index>      $indexes     by name, the unique ones among them
     * @param array<string, ForeignKey> $foreignKeys by name
     */
    public function __construct(
        public readonly string $name,
        public readonly array $columns = [],
        public readonly array $primaryKey = [],
        public readonly array $indexes = [],
        public readonly array $foreignKeys = [],
    ) {
    }

    /** @throws \OutOfBoundsException for a column the table does not hold */
    public function column(string $name): Column
    {
        return $this->columns[$name] ?? throw new \OutOfBoundsException(\sprintf('No column %s.%s.', $this->name, $name));
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'],
            array_map(Column::fromArray(...), $data['columns'] ?? []),
            $data['primaryKey'] ?? [],
            array_map(Index::fromArray(...), $data['indexes'] ?? []),
            array_map(ForeignKey::fromArray(...), $data['foreignKeys'] ?? []),
        );
    }
}
