<?php

namespace Omnibridge\Schema\Database;

use Omnibridge\Schema\Column;

/**
 * What an application's own column types are, as data, for the databases
 * this package writes and reads (MySqlStatements, SqliteStatements,
 * MySqlSchema, SqliteSchema) - no ORM asked. A description names a column's
 * type by the name the application gave it (thread_state, passkey_base64);
 * here each such name is one of three things:
 *
 * - an alias of a portable type (passkey_base64 is a text, passkey_trust_path
 *   a json): the column is written as that type, its length and the rest kept;
 * - an enumeration: its values, in order - an ENUM on MySQL, a CLOB on SQLite;
 * - a set: its values, in order - a SET on MySQL, a CLOB on SQLite.
 *
 * resolve() gives the column with its portable type ('enum' and 'set' with
 * their values for the last two); toArray() and fromArray() keep it beside a
 * description in a file.
 */
final class ColumnTypes
{
    /**
     * @param array<string, string>       $aliases      a type's name => the portable type it is written as
     * @param array<string, list<string>> $enumerations a type's name => its values
     * @param array<string, list<string>> $sets         a type's name => its values
     */
    public function __construct(
        public readonly array $aliases = [],
        public readonly array $enumerations = [],
        public readonly array $sets = [],
    ) {
    }

    /** The column with its portable type: an alias followed, an enumeration as 'enum' and a set as 'set' with their values. */
    public function resolve(Column $column): Column
    {
        $type = $column->type;
        for ($seen = []; isset($this->aliases[$type]) && !isset($seen[$type]); $type = $this->aliases[$type]) {
            $seen[$type] = true;
        }

        [$type, $values] = match (true) {
            isset($this->enumerations[$type]) => ['enum', array_values($this->enumerations[$type])],
            isset($this->sets[$type]) => ['set', array_values($this->sets[$type])],
            default => [$type, $column->values],
        };

        if ($type === $column->type && $values === $column->values) {
            return $column;
        }

        return new Column($column->name, $type, $column->nullable, $column->length, $column->precision, $column->scale, $column->fixed, $column->unsigned, $column->autoincrement, $column->default, $values);
    }

    /** These types, then those given over them. */
    public function with(self $more): self
    {
        return new self($more->aliases + $this->aliases, $more->enumerations + $this->enumerations, $more->sets + $this->sets);
    }

    /**
     * The same types as MySqlSchema::read()'s $types: a closure per type's name giving the SQL type MySQL writes.
     *
     * @return array<string, \Closure(Column): string>
     */
    public function mysql(): array
    {
        $schema = MySqlSchema::of([]);
        $types = [];
        foreach (array_keys($this->aliases + $this->enumerations + $this->sets) as $name) {
            $types[$name] = fn (Column $column): string => $schema->columnType($this->resolve(new Column($column->name, $name, $column->nullable, $column->length, $column->precision, $column->scale, $column->fixed, $column->unsigned, $column->autoincrement, $column->default, $column->values)));
        }

        return $types;
    }

    /** @return array{aliases: array<string, string>, enumerations: array<string, list<string>>, sets: array<string, list<string>>} */
    public function toArray(): array
    {
        return ['aliases' => $this->aliases, 'enumerations' => $this->enumerations, 'sets' => $this->sets];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['aliases'] ?? [], $data['enumerations'] ?? [], $data['sets'] ?? []);
    }
}
