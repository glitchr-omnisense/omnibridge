<?php

namespace Omnibridge\Schema\Database;

use Omnibridge\Schema\Column;
use Omnibridge\Schema\Description;
use Omnibridge\Schema\ForeignKey;
use Omnibridge\Schema\Table;

/**
 * The CREATE statements of a description's tables for SQLite, written from the
 * description alone - no ORM, no DBAL: a CREATE TABLE per table, in the
 * description's order (by name), with its columns, its primary key and its
 * foreign keys inside (SQLite adds none later), then a CREATE INDEX or CREATE
 * UNIQUE INDEX per index (a fulltext or spatial flag means nothing to SQLite:
 * a plain index, as DBAL writes it).
 *
 * The SQL types are those DBAL 4 declares on SQLite, which SQLite keeps as
 * written (PRAGMA table_info): VARCHAR(n), CHAR(n), CLOB for any text, JSON,
 * serialized array, enumeration and set, INTEGER, SMALLINT, BIGINT, BOOLEAN,
 * DOUBLE PRECISION, REAL, NUMERIC(p, s), DATETIME, DATE, TIME, BLOB; an
 * auto-increment column is INTEGER PRIMARY KEY AUTOINCREMENT (the table then
 * has no other primary key clause). Defaults as DBAL writes them (Literals),
 * DEFAULT NULL on a nullable column without one; foreign keys NOT DEFERRABLE
 * INITIALLY IMMEDIATE. Every name is quoted with double quotes.
 */
final class SqliteStatements implements Statements
{
    public function __construct(private readonly ColumnTypes $types = new ColumnTypes())
    {
    }

    public function driver(): string
    {
        return 'sqlite';
    }

    public function create(Description $description): array
    {
        $statements = [];
        foreach ($description->tables as $table) {
            $statements[] = $this->table($table);
            foreach ($table->indexes as $index) {
                $statements[] = 'CREATE '.($index->unique ? 'UNIQUE ' : '').'INDEX '.self::quote($index->name).' ON '.self::quote($table->name).' ('.self::quoteAll($index->columns).')';
            }
        }

        return $statements;
    }

    /** The CREATE TABLE of one table: its columns, primary key and foreign keys. */
    public function table(Table $table): string
    {
        if ([] === $table->columns) {
            throw new \LogicException(\sprintf('The table %s has no column.', $table->name));
        }

        $parts = [];
        $autoincrement = false;
        foreach ($table->columns as $column) {
            $parts[] = $this->column($column);
            $autoincrement = $autoincrement || $column->autoincrement;
        }
        if (!$autoincrement && [] !== $table->primaryKey) {
            $parts[] = 'PRIMARY KEY ('.self::quoteAll($table->primaryKey).')';
        }
        foreach ($table->foreignKeys as $foreignKey) {
            $parts[] = self::foreignKey($foreignKey);
        }

        return 'CREATE TABLE '.self::quote($table->name).' ('.implode(', ', $parts).')';
    }

    /** A column's declaration: its name, SQL type, default, NOT NULL. */
    public function column(Column $column): string
    {
        $resolved = $this->types->resolve($column);
        $declaration = self::quote($column->name).' '.$this->sqlType($resolved);

        $default = Literals::default($resolved);
        if (null !== $default) {
            $declaration .= ' DEFAULT '.$default;
        } elseif ($column->nullable) {
            $declaration .= ' DEFAULT NULL';
        }

        return $declaration.($column->nullable ? '' : ' NOT NULL');
    }

    /** The SQL type SQLite keeps for a column (portable type, ColumnTypes::resolve()'d), as DBAL 4 declares it. */
    public function sqlType(Column $column): string
    {
        if ($column->autoincrement && \in_array($column->type, ['integer', 'smallint', 'bigint'], true)) {
            return 'INTEGER PRIMARY KEY AUTOINCREMENT';
        }

        $unsigned = $column->unsigned ? ' UNSIGNED' : '';

        return match ($column->type) {
            'string', 'ascii_string' => ($column->fixed ? 'CHAR(' : 'VARCHAR(').($column->length ?? 255).')',
            'text', 'json', 'array', 'simple_array', 'object', 'enum', 'set' => 'CLOB',
            'integer' => 'INTEGER'.$unsigned,
            'smallint' => 'SMALLINT'.$unsigned,
            'bigint' => 'BIGINT'.$unsigned,
            'boolean' => 'BOOLEAN',
            'float' => 'DOUBLE PRECISION',
            'smallfloat' => 'REAL',
            'decimal' => 'NUMERIC('.($column->precision ?? 10).', '.($column->scale ?? 0).')',
            'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable', 'utc_datetime', 'utc_datetime_immutable' => 'DATETIME',
            'date', 'date_immutable' => 'DATE',
            'time', 'time_immutable' => 'TIME',
            'guid' => 'CHAR(36)',
            'binary', 'blob' => 'BLOB',
            'dateinterval' => 'VARCHAR(255)',
            default => throw new \UnexpectedValueException(\sprintf('No SQL type for "%s" (column %s): give it in the ColumnTypes.', $column->type, $column->name)),
        };
    }

    private static function foreignKey(ForeignKey $foreignKey): string
    {
        return 'CONSTRAINT '.self::quote($foreignKey->name).' FOREIGN KEY ('.self::quoteAll($foreignKey->columns).') REFERENCES '.self::quote($foreignKey->table).' ('.self::quoteAll($foreignKey->referencedColumns).')'
            .(null === $foreignKey->onUpdate ? '' : ' ON UPDATE '.strtoupper($foreignKey->onUpdate))
            .(null === $foreignKey->onDelete ? '' : ' ON DELETE '.strtoupper($foreignKey->onDelete))
            .' NOT DEFERRABLE INITIALLY IMMEDIATE';
    }

    private static function quote(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }

    /** @param list<string> $names */
    private static function quoteAll(array $names): string
    {
        return implode(', ', array_map(self::quote(...), $names));
    }
}
