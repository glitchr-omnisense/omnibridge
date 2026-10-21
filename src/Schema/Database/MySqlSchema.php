<?php

namespace Omnibridge\Schema\Database;

use Omnibridge\Schema\Column;
use Omnibridge\Schema\Description;
use Omnibridge\Schema\Table;

/**
 * A MySQL database's schema read from its information_schema (through PDO,
 * read-only, no ORM), and compared with a description: every table, then in
 * each every column - its SQL type as MySQL 8 writes it, nullable, default,
 * auto-increment -, the primary key, each index and unique index by name and
 * columns, each foreign key by name, columns, referenced table and columns,
 * and what a deletion does. An independent check that the description is the
 * database's schema: nothing of the persistence that made the tables is asked.
 *
 * A column's expected SQL type comes from its portable type (columnType():
 * string(255) is varchar(255), text longtext, boolean tinyint(1), every date
 * and time datetime...); an application's own types are given by name, each a
 * closure from the column to its SQL type (enum('A','B') for an enumeration).
 * Collations, character sets and comments are not compared.
 */
final class MySqlSchema
{
    /** @var array<string, array{columns: array<string, array{type: string, nullable: bool, default: ?string, autoincrement: bool}>, primaryKey: list<string>, indexes: array<string, array{columns: list<string>, unique: bool, flags: list<string>}>, foreignKeys: array<string, array{columns: list<string>, table: string, referencedColumns: list<string>, onDelete: ?string, onUpdate: ?string}>}> */
    public readonly array $tables;

    /** @param array<string, \Closure(Column): string> $types */
    private function __construct(array $tables, private readonly array $types)
    {
        $this->tables = $tables;
    }

    /**
     * A schema kept as data (read() once, written to a file), or hand-written: the tables as read() gives them.
     *
     * @param array<string, array<string, mixed>>   $tables by name
     * @param array<string, \Closure(Column): string> $types
     */
    public static function of(array $tables, array $types = []): self
    {
        return new self($tables, $types);
    }

    /**
     * @param list<string>                          $ignore tables of the database the description does not hold (doctrine_migration_versions...)
     * @param array<string, \Closure(Column): string> $types the SQL type of a column of an application's own type, by the type's name
     */
    public static function read(\PDO $pdo, ?string $database = null, array $ignore = [], array $types = []): self
    {
        $database ??= (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $query = static function (string $sql) use ($pdo, $database): array {
            $statement = $pdo->prepare($sql);
            $statement->execute([$database]);

            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        };

        $tables = [];
        foreach ($query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME") as $row) {
            if (!\in_array($row['TABLE_NAME'], $ignore, true)) {
                $tables[$row['TABLE_NAME']] = ['columns' => [], 'primaryKey' => [], 'indexes' => [], 'foreignKeys' => []];
            }
        }

        foreach ($query('SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION') as $row) {
            if (isset($tables[$row['TABLE_NAME']])) {
                $tables[$row['TABLE_NAME']]['columns'][$row['COLUMN_NAME']] = [
                    'type' => $row['COLUMN_TYPE'],
                    'nullable' => 'YES' === $row['IS_NULLABLE'],
                    'default' => $row['COLUMN_DEFAULT'],
                    'autoincrement' => str_contains((string) $row['EXTRA'], 'auto_increment'),
                ];
            }
        }

        foreach ($query('SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, NON_UNIQUE, INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX') as $row) {
            if (!isset($tables[$row['TABLE_NAME']])) {
                continue;
            }

            if ('PRIMARY' === $row['INDEX_NAME']) {
                $tables[$row['TABLE_NAME']]['primaryKey'][] = $row['COLUMN_NAME'];
                continue;
            }

            $index = &$tables[$row['TABLE_NAME']]['indexes'][$row['INDEX_NAME']];
            $index ??= ['columns' => [], 'unique' => '0' === (string) $row['NON_UNIQUE'], 'flags' => \in_array($row['INDEX_TYPE'], ['FULLTEXT', 'SPATIAL'], true) ? [strtolower($row['INDEX_TYPE'])] : []];
            $index['columns'][] = $row['COLUMN_NAME'];
            unset($index);
        }

        $rules = [];
        foreach ($query('SELECT CONSTRAINT_NAME, TABLE_NAME, DELETE_RULE, UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ?') as $row) {
            $rules[$row['TABLE_NAME']][$row['CONSTRAINT_NAME']] = [self::rule($row['DELETE_RULE']), self::rule($row['UPDATE_RULE'])];
        }

        foreach ($query('SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION') as $row) {
            if (!isset($tables[$row['TABLE_NAME']])) {
                continue;
            }

            $foreignKey = &$tables[$row['TABLE_NAME']]['foreignKeys'][$row['CONSTRAINT_NAME']];
            $foreignKey ??= ['columns' => [], 'table' => $row['REFERENCED_TABLE_NAME'], 'referencedColumns' => [], 'onDelete' => $rules[$row['TABLE_NAME']][$row['CONSTRAINT_NAME']][0] ?? null, 'onUpdate' => $rules[$row['TABLE_NAME']][$row['CONSTRAINT_NAME']][1] ?? null];
            $foreignKey['columns'][] = $row['COLUMN_NAME'];
            $foreignKey['referencedColumns'][] = $row['REFERENCED_COLUMN_NAME'];
            unset($foreignKey);
        }

        return new self($tables, $types);
    }

    /**
     * Every difference between the description's tables and the database's, one a line ("table.column: type
     * varchar(255) / varchar(191)"); none when the description is the database's schema.
     *
     * @return list<string>
     */
    public function differences(Description $description): array
    {
        $differences = [];
        foreach (array_diff_key($description->tables, $this->tables) as $name => $_) {
            $differences[] = $name.': a table of the description, not of the database';
        }
        foreach (array_diff_key($this->tables, $description->tables) as $name => $_) {
            $differences[] = $name.': a table of the database, not of the description';
        }

        foreach (array_intersect_key($description->tables, $this->tables) as $name => $table) {
            $differences = array_merge($differences, $this->tableDifferences($table, $this->tables[$name]));
        }

        return $differences;
    }

    /** @return list<string> */
    private function tableDifferences(Table $table, array $actual): array
    {
        $name = $table->name;
        $differences = [];

        $expectedColumns = array_keys($table->columns);
        $actualColumns = array_keys($actual['columns']);
        if (array_diff($expectedColumns, $actualColumns) || array_diff($actualColumns, $expectedColumns)) {
            $differences[] = $name.': columns '.json_encode(array_values(array_diff($expectedColumns, $actualColumns))).' missing, '.json_encode(array_values(array_diff($actualColumns, $expectedColumns))).' more';
        }

        foreach (array_intersect_key($table->columns, $actual['columns']) as $columnName => $column) {
            $expected = [
                'type' => $this->columnType($column),
                'nullable' => $column->nullable,
                'default' => self::default($column),
                'autoincrement' => $column->autoincrement,
            ];
            foreach ($expected as $key => $value) {
                if ($value !== $actual['columns'][$columnName][$key]) {
                    $differences[] = $name.'.'.$columnName.': '.$key.' '.json_encode($value).' / '.json_encode($actual['columns'][$columnName][$key]);
                }
            }
        }

        if ($table->primaryKey !== $actual['primaryKey']) {
            $differences[] = $name.': primary key '.json_encode($table->primaryKey).' / '.json_encode($actual['primaryKey']);
        }

        $indexes = [];
        foreach ($table->indexes as $index) {
            $indexes[$index->name] = ['columns' => $index->columns, 'unique' => $index->unique, 'flags' => $index->flags];
        }
        $differences = array_merge($differences, self::named($name, 'index', $indexes, $actual['indexes']));

        $foreignKeys = [];
        foreach ($table->foreignKeys as $foreignKey) {
            $foreignKeys[$foreignKey->name] = ['columns' => $foreignKey->columns, 'table' => $foreignKey->table, 'referencedColumns' => $foreignKey->referencedColumns, 'onDelete' => $foreignKey->onDelete, 'onUpdate' => $foreignKey->onUpdate];
        }

        return array_merge($differences, self::named($name, 'foreign key', $foreignKeys, $actual['foreignKeys']));
    }

    /**
     * @param array<string, array<string, mixed>> $expected
     * @param array<string, array<string, mixed>> $actual
     *
     * @return list<string>
     */
    private static function named(string $table, string $what, array $expected, array $actual): array
    {
        $differences = [];
        foreach ($expected as $name => $item) {
            if (!isset($actual[$name])) {
                $differences[] = $table.': '.$what.' '.$name.' '.json_encode($item).' missing';
            } elseif ($item != $actual[$name]) {
                $differences[] = $table.': '.$what.' '.$name.' '.json_encode($item).' / '.json_encode($actual[$name]);
            }
        }
        foreach (array_diff_key($actual, $expected) as $name => $item) {
            $differences[] = $table.': '.$what.' '.$name.' '.json_encode($item).' more';
        }

        return $differences;
    }

    /** The SQL type MySQL 8 writes for a column of the description (information_schema's COLUMN_TYPE). */
    public function columnType(Column $column): string
    {
        if (isset($this->types[$column->type])) {
            return ($this->types[$column->type])($column);
        }

        $unsigned = $column->unsigned ? ' unsigned' : '';

        return match ($column->type) {
            'string', 'ascii_string' => ($column->fixed ? 'char(' : 'varchar(').($column->length ?? 255).')',
            'text' => match (true) {
                null === $column->length => 'longtext',
                $column->length <= 255 => 'tinytext',
                $column->length <= 65535 => 'text',
                $column->length <= 16777215 => 'mediumtext',
                default => 'longtext',
            },
            'integer' => 'int'.$unsigned,
            'smallint' => 'smallint'.$unsigned,
            'bigint' => 'bigint'.$unsigned,
            'boolean' => 'tinyint(1)',
            'float' => 'double',
            'smallfloat' => 'float',
            'decimal' => 'decimal('.($column->precision ?? 10).','.($column->scale ?? 0).')'.$unsigned,
            'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable', 'utc_datetime', 'utc_datetime_immutable' => 'datetime',
            'date', 'date_immutable' => 'date',
            'time', 'time_immutable' => 'time',
            'json' => 'json',
            'guid' => 'char(36)',
            'binary' => ($column->fixed ? 'binary(' : 'varbinary(').($column->length ?? 255).')',
            'blob' => 'longblob',
            'array', 'simple_array', 'object' => 'longtext',
            'dateinterval' => 'varchar(255)',
            default => throw new \UnexpectedValueException(\sprintf('No SQL type for "%s" (column %s): give it in $types.', $column->type, $column->name)),
        };
    }

    /** enum('A','B') of the column's values: the SQL type of an enumeration. */
    public static function enumeration(Column $column): string
    {
        return 'enum('.self::quoted($column->values ?? []).')';
    }

    /** set('A','B') of the column's values: the SQL type of a set. */
    public static function set(Column $column): string
    {
        return 'set('.self::quoted($column->values ?? []).')';
    }

    /** @param list<string> $values */
    private static function quoted(array $values): string
    {
        return implode(',', array_map(static fn (string $value) => "'".str_replace("'", "''", $value)."'", $values));
    }

    private static function default(Column $column): ?string
    {
        return match (true) {
            null === $column->default => null,
            \is_bool($column->default) => $column->default ? '1' : '0',
            default => (string) $column->default,
        };
    }

    private static function rule(?string $rule): ?string
    {
        return null === $rule || \in_array(strtoupper($rule), ['RESTRICT', 'NO ACTION'], true) ? null : strtoupper($rule);
    }
}
