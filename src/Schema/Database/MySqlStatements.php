<?php

namespace Omnibridge\Schema\Database;

use Omnibridge\Schema\Column;
use Omnibridge\Schema\Description;
use Omnibridge\Schema\ForeignKey;
use Omnibridge\Schema\Index;
use Omnibridge\Schema\Table;

/**
 * The CREATE statements of a description's tables for MySQL 8, written from
 * the description alone - no ORM, no DBAL: a CREATE TABLE per table, in the
 * description's order (by name), its columns in their order, its indexes and
 * unique indexes (fulltext and spatial ones by their flag) and its primary key
 * inside, the table's character set, collation and engine after; then an
 * ALTER TABLE ... ADD CONSTRAINT per foreign key, once every table exists (a
 * table may reference one created after it, or itself).
 *
 * A column's SQL type is MySqlSchema::columnType()'s, what information_schema
 * reads back, but a boolean's: TINYINT, as DBAL 4 writes it (MySQL 8 reads
 * tinyint, where TINYINT(1) reads tinyint(1)); an application's own types are
 * resolved by ColumnTypes first (an alias, an ENUM, a SET). The defaults are
 * written as DBAL writes them (Literals), none on a TEXT or a BLOB column
 * (MySQL refuses a literal there); a nullable column without one gets DEFAULT
 * NULL. Every name is quoted with backticks.
 *
 * So the database these statements make reads back, column for column, as the
 * one Doctrine's SchemaTool makes from the same mapping (plan "omnibase
 * agnostic", step F7a: information_schema compared on lesringostars).
 */
final class MySqlStatements implements Statements
{
    /**
     * @param string      $charset   the tables' character set
     * @param string|null $collation the tables' collation, null for the character set's default
     * @param string      $engine    the tables' engine
     */
    public function __construct(
        private readonly ColumnTypes $types = new ColumnTypes(),
        private readonly string $charset = 'utf8mb4',
        private readonly ?string $collation = 'utf8mb4_unicode_ci',
        private readonly string $engine = 'InnoDB',
    ) {
    }

    public function driver(): string
    {
        return 'mysql';
    }

    public function create(Description $description): array
    {
        $statements = [];
        foreach ($description->tables as $table) {
            $statements[] = $this->table($table);
        }
        foreach ($description->tables as $table) {
            foreach ($table->foreignKeys as $foreignKey) {
                $statements[] = 'ALTER TABLE '.self::quote($table->name).' ADD '.self::foreignKey($foreignKey);
            }
        }

        return $statements;
    }

    /** The CREATE TABLE of one table: its columns, indexes and primary key, its options. */
    public function table(Table $table): string
    {
        if ([] === $table->columns) {
            throw new \LogicException(\sprintf('The table %s has no column.', $table->name));
        }

        $parts = [];
        foreach ($table->columns as $column) {
            $parts[] = $this->column($column);
        }
        foreach ($table->indexes as $index) {
            $parts[] = self::index($index);
        }
        if ([] !== $table->primaryKey) {
            $parts[] = 'PRIMARY KEY ('.self::quoteAll($table->primaryKey).')';
        }

        $options = 'DEFAULT CHARACTER SET '.$this->charset.(null === $this->collation ? '' : ' COLLATE `'.$this->collation.'`').' ENGINE = '.$this->engine;

        return 'CREATE TABLE '.self::quote($table->name).' ('.implode(', ', $parts).') '.$options;
    }

    /** A column's declaration: its name, SQL type, default, NOT NULL, AUTO_INCREMENT. */
    public function column(Column $column): string
    {
        $resolved = $this->types->resolve($column);
        $type = $this->sqlType($resolved);

        $default = \in_array($resolved->type, ['text', 'blob', 'array', 'simple_array', 'object'], true) ? null : Literals::default($resolved, true);
        $declaration = self::quote($column->name).' '.$type.($column->autoincrement ? ' AUTO_INCREMENT' : '');
        if (null !== $default) {
            $declaration .= ' DEFAULT '.$default;
        } elseif ($column->nullable) {
            $declaration .= ' DEFAULT NULL';
        }

        return $declaration.($column->nullable ? '' : ' NOT NULL');
    }

    /** The SQL type written for a column (portable type, ColumnTypes::resolve()'d). */
    public function sqlType(Column $column): string
    {
        return 'boolean' === $column->type ? 'tinyint' : MySqlSchema::of([])->columnType($column);
    }

    private static function index(Index $index): string
    {
        $kind = match (true) {
            $index->unique => 'UNIQUE INDEX',
            \in_array('fulltext', $index->flags, true) => 'FULLTEXT INDEX',
            \in_array('spatial', $index->flags, true) => 'SPATIAL INDEX',
            default => 'INDEX',
        };

        return $kind.' '.self::quote($index->name).' ('.self::quoteAll($index->columns).')';
    }

    private static function foreignKey(ForeignKey $foreignKey): string
    {
        return 'CONSTRAINT '.self::quote($foreignKey->name).' FOREIGN KEY ('.self::quoteAll($foreignKey->columns).') REFERENCES '.self::quote($foreignKey->table).' ('.self::quoteAll($foreignKey->referencedColumns).')'
            .(null === $foreignKey->onUpdate ? '' : ' ON UPDATE '.strtoupper($foreignKey->onUpdate))
            .(null === $foreignKey->onDelete ? '' : ' ON DELETE '.strtoupper($foreignKey->onDelete));
    }

    private static function quote(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    /** @param list<string> $names */
    private static function quoteAll(array $names): string
    {
        return implode(', ', array_map(self::quote(...), $names));
    }
}
