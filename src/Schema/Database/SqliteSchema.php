<?php

namespace Omnibridge\Schema\Database;

use Omnibridge\Schema\Description;
use Omnibridge\Schema\Table;

/**
 * An SQLite database's schema read through PDO (sqlite_master and the PRAGMAs,
 * read-only, no ORM), in MySqlSchema's shape, and compared with a description
 * or with another database read: every table, then in each every column - its
 * SQL type as declared (SQLite keeps it as written), nullable, default (the
 * literal as written: 'draft', 0, NULL), auto-increment -, the primary key,
 * each index and unique index by name and columns (the primary key's own
 * index left out), each foreign key by name (read from the table's statement:
 * SQLite's PRAGMA has no names), columns, referenced table and columns, and
 * what a deletion and an update do.
 *
 * A column's expected SQL type and default are SqliteStatements' (the types
 * DBAL 4 declares on SQLite); an application's own types are resolved by the
 * ColumnTypes given. Fulltext and spatial flags mean nothing to SQLite: an
 * index has none.
 */
final class SqliteSchema
{
    /** @var array<string, array{columns: array<string, array{type: string, nullable: bool, default: ?string, autoincrement: bool}>, primaryKey: list<string>, indexes: array<string, array{columns: list<string>, unique: bool, flags: list<string>}>, foreignKeys: array<string, array{columns: list<string>, table: string, referencedColumns: list<string>, onDelete: ?string, onUpdate: ?string}>}> */
    public readonly array $tables;

    private function __construct(array $tables, private readonly ColumnTypes $types)
    {
        $this->tables = $tables;
    }

    /** @param array<string, array<string, mixed>> $tables by name, as read() gives them */
    public static function of(array $tables, ColumnTypes $types = new ColumnTypes()): self
    {
        return new self($tables, $types);
    }

    /** @param list<string> $ignore tables of the database the description does not hold */
    public static function read(\PDO $pdo, array $ignore = [], ColumnTypes $types = new ColumnTypes()): self
    {
        $tables = [];
        foreach ($pdo->query("SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite\\_%' ESCAPE '\\' ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (\in_array($row['name'], $ignore, true)) {
                continue;
            }
            $tables[$row['name']] = self::table($pdo, $row['name'], (string) $row['sql']);
        }

        return new self($tables, $types);
    }

    /** @return array<string, mixed> */
    private static function table(\PDO $pdo, string $name, string $sql): array
    {
        $quoted = '"'.str_replace('"', '""', $name).'"';
        $table = ['columns' => [], 'primaryKey' => [], 'indexes' => [], 'foreignKeys' => []];

        $primary = [];
        foreach ($pdo->query('PRAGMA table_info('.$quoted.')')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $column = (string) $row['name'];
            $table['columns'][$column] = [
                'type' => (string) $row['type'],
                'nullable' => 0 === (int) $row['notnull'],
                'default' => null === $row['dflt_value'] ? null : (string) $row['dflt_value'],
                'autoincrement' => (int) $row['pk'] > 0 && 1 === preg_match('/(?:^|[(,])\s*["`\[]?'.preg_quote($column, '/').'["`\]]?\s+INTEGER\s+PRIMARY\s+KEY\s+AUTOINCREMENT/i', $sql),
            ];
            if ((int) $row['pk'] > 0) {
                $primary[(int) $row['pk']] = $column;
            }
        }
        ksort($primary);
        $table['primaryKey'] = array_values($primary);

        foreach ($pdo->query('PRAGMA index_list('.$quoted.')')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if ('pk' === ($row['origin'] ?? null)) {
                continue;
            }
            $columns = [];
            foreach ($pdo->query('PRAGMA index_info("'.str_replace('"', '""', (string) $row['name']).'")')->fetchAll(\PDO::FETCH_ASSOC) as $info) {
                $columns[(int) $info['seqno']] = (string) $info['name'];
            }
            ksort($columns);
            $table['indexes'][(string) $row['name']] = ['columns' => array_values($columns), 'unique' => 1 === (int) $row['unique'], 'flags' => []];
        }
        ksort($table['indexes']);

        // The constraints' names, which only the statement keeps: CONSTRAINT <name> FOREIGN KEY (<columns>) REFERENCES <table>.
        $names = [];
        if (preg_match_all('/CONSTRAINT\s+["`\[]?([^"`\]\s]+)["`\]]?\s+FOREIGN\s+KEY\s*\(([^)]*)\)\s*REFERENCES\s+["`\[]?([^"`\]\s(]+)/i', $sql, $matches, \PREG_SET_ORDER)) {
            foreach ($matches as [, $constraint, $columns, $referenced]) {
                $names[self::unquoted($columns).'>'.$referenced] = $constraint;
            }
        }
        $keys = [];
        foreach ($pdo->query('PRAGMA foreign_key_list('.$quoted.')')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $key = &$keys[(int) $row['id']];
            $key ??= ['columns' => [], 'table' => (string) $row['table'], 'referencedColumns' => [], 'onDelete' => self::rule($row['on_delete'] ?? null), 'onUpdate' => self::rule($row['on_update'] ?? null), 'seq' => []];
            $key['seq'][(int) $row['seq']] = [(string) $row['from'], (string) $row['to']];
            unset($key);
        }
        foreach ($keys as $id => $key) {
            ksort($key['seq']);
            $key['columns'] = array_column($key['seq'], 0);
            $key['referencedColumns'] = array_column($key['seq'], 1);
            unset($key['seq']);
            $table['foreignKeys'][$names[implode(',', $key['columns']).'>'.$key['table']] ?? 'fk_'.$id] = $key;
        }
        ksort($table['foreignKeys']);

        return $table;
    }

    /**
     * Every difference between the description's tables and the database's, one a line ("thread.slug: type
     * "VARCHAR(255)" / "VARCHAR(191)""); none when the description is the database's schema.
     *
     * @return list<string>
     */
    public function differences(Description $description): array
    {
        $statements = new SqliteStatements($this->types);
        $expected = [];
        foreach ($description->tables as $name => $table) {
            $expected[$name] = $this->expected($table, $statements);
        }

        return array_map(
            static fn (string $line) => str_replace([': a table of the first database only', ': a table of the second database only'], [': a table of the description, not of the database', ': a table of the database, not of the description'], $line),
            MySqlSchema::compare($expected, $this->tables),
        );
    }

    /**
     * Every difference between this schema and another read one (the same lines as MySqlSchema::against()).
     *
     * @return list<string>
     */
    public function against(self $other): array
    {
        return MySqlSchema::compare($this->tables, $other->tables);
    }

    /** @return array<string, mixed> a table of the description as read() would read it from the database SqliteStatements make */
    private function expected(Table $table, SqliteStatements $statements): array
    {
        $columns = [];
        foreach ($table->columns as $name => $column) {
            $resolved = $this->types->resolve($column);
            $default = Literals::default($resolved);
            $columns[$name] = [
                'type' => preg_replace('/ PRIMARY KEY AUTOINCREMENT$/', '', $statements->sqlType($resolved)),
                'nullable' => $column->nullable,
                'default' => $default ?? ($column->nullable ? 'NULL' : null),
                'autoincrement' => $column->autoincrement,
            ];
        }

        $indexes = [];
        foreach ($table->indexes as $index) {
            $indexes[$index->name] = ['columns' => $index->columns, 'unique' => $index->unique, 'flags' => []];
        }
        ksort($indexes);

        $foreignKeys = [];
        foreach ($table->foreignKeys as $foreignKey) {
            $foreignKeys[$foreignKey->name] = ['columns' => $foreignKey->columns, 'table' => $foreignKey->table, 'referencedColumns' => $foreignKey->referencedColumns, 'onDelete' => self::rule($foreignKey->onDelete), 'onUpdate' => self::rule($foreignKey->onUpdate)];
        }
        ksort($foreignKeys);

        return ['columns' => $columns, 'primaryKey' => $table->primaryKey, 'indexes' => $indexes, 'foreignKeys' => $foreignKeys];
    }

    private static function unquoted(string $columns): string
    {
        return implode(',', array_map(static fn (string $column) => trim($column, " \t\n\"`[]"), explode(',', $columns)));
    }

    private static function rule(?string $rule): ?string
    {
        return null === $rule || \in_array(strtoupper($rule), ['RESTRICT', 'NO ACTION'], true) ? null : strtoupper($rule);
    }
}
