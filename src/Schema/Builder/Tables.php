<?php

namespace Omnibridge\Schema\Builder;

use Omnibridge\Schema\Builder;
use Omnibridge\Schema\Column;
use Omnibridge\Schema\ForeignKey;
use Omnibridge\Schema\Index;
use Omnibridge\Schema\Naming;
use Omnibridge\Schema\Table;

/**
 * The tables a set of described classes make, as Doctrine's SchemaTool
 * creates them over DBAL - so that the description finds the tables that
 * exist: one table for a hierarchy stored in one table (its subclasses'
 * columns nullable), one per class of a joined hierarchy (a subclass's
 * identifier a foreign key to the root's table, on delete cascade), a join
 * table per owning many-to-many relation; the discriminator column in the
 * root's table; a column per field, a join column per owning to-one relation
 * (of its referenced column's type); a unique index per unique column, the
 * indexes and unique constraints the classes declare, an index backing each
 * foreign key that no index of the same columns already backs, each named as
 * DBAL names what nobody named (Naming::generatedName()).
 *
 * @internal
 */
final class Tables
{
    /** @var array<string, array{name: string, columns: array<string, array<string, mixed>>, indexes: array<string, array{columns: list<string>, unique: bool, primary: bool, flags: list<string>}>, implicit: array<string, true>, foreignKeys: array<string, array<string, mixed>>}> */
    private array $tables = [];

    /** @var array<string, array<string, mixed>> table.columns => the foreign key to add once every table is built */
    private array $foreignKeys = [];

    /** @var array<string, true> */
    private array $blacklisted = [];

    public function __construct(
        private readonly Naming $naming,
        private readonly Builder $builder,
    ) {
    }

    /**
     * @param list<Draft> $classes
     *
     * @return array<string, Table> by name
     */
    public function of(array $classes): array
    {
        $processed = [];
        foreach ($classes as $class) {
            if (isset($processed[$class->name]) || $class->isMappedSuperclass || $class->isEmbeddedClass || ($class->isInheritanceTypeSingleTable() && !$class->isRootEntity())) {
                continue;
            }

            $table = $class->table['name'];
            $this->create($table);

            if ($class->isInheritanceTypeSingleTable()) {
                $this->columns($class, $table);
                $this->relations($class, $table);
                $this->discriminator($class, $table);
                foreach ($class->subClasses as $subClassName) {
                    $subClass = $this->builder->draft($subClassName);
                    $this->columns($subClass, $table);
                    $this->relations($subClass, $table);
                    $processed[$subClassName] = true;
                }
            } elseif ($class->isInheritanceTypeJoined()) {
                foreach ($class->fieldMappings as $mapping) {
                    if (null === $mapping['inherited']) {
                        $this->column($class, $mapping, $table);
                    }
                }
                $this->relations($class, $table);

                if ($class->isRootEntity()) {
                    $this->discriminator($class, $table);
                } else {
                    $keys = [];
                    foreach ($class->identifier as $property) {
                        if (null !== ($class->fieldMappings[$property]['inherited'] ?? null)) {
                            $this->column($class, $class->fieldMappings[$property], $table);
                            $this->tables[$table]['columns'][$class->fieldMappings[$property]['columnName']]['autoincrement'] = false;
                            $keys[] = $class->fieldMappings[$property]['columnName'];
                        } elseif (null !== ($class->associationMappings[$property]['inherited'] ?? null)) {
                            foreach ($class->associationMappings[$property]['joinColumns'] as $joinColumn) {
                                $keys[] = $joinColumn['name'];
                            }
                        }
                    }

                    if ($keys) {
                        unset($this->foreignKeys[$table.'.'.implode('', $keys)]);
                        $this->addForeignKey($table, $this->builder->draft($class->rootEntityName)->table['name'], $keys, $keys, 'CASCADE', null);
                        $this->addIndex($table, 'PRIMARY', $keys, true, true);
                    }
                }
            } else {
                $this->columns($class, $table);
                $this->relations($class, $table);
            }

            $primaryKey = [];
            foreach ($class->identifier as $property) {
                if (isset($class->fieldMappings[$property])) {
                    $primaryKey[] = $class->fieldMappings[$property]['columnName'];
                } elseif (isset($class->associationMappings[$property])) {
                    foreach ($class->associationMappings[$property]['joinColumns'] as $joinColumn) {
                        $primaryKey[] = $joinColumn['name'];
                    }
                }
            }

            if (!isset($this->tables[$table]['indexes']['PRIMARY'])) {
                $this->addIndex($table, 'PRIMARY', $primaryKey, true, true);
            }

            // An index the primary key spans (its columns first) is the primary key's.
            $primary = $this->tables[$table]['indexes']['PRIMARY'];
            foreach ($this->tables[$table]['indexes'] as $name => $index) {
                if ('PRIMARY' !== $name && self::spans($primary['columns'], $index['columns'])) {
                    unset($this->tables[$table]['indexes'][$name], $this->tables[$table]['implicit'][$name]);
                }
            }

            foreach ($class->table['indexes'] ?? [] as $name => $index) {
                $this->addIndex($table, \is_int($name) ? null : $name, $this->indexColumns($class, $index), false, false, (array) ($index['flags'] ?? []));
            }

            foreach ($class->table['uniqueConstraints'] ?? [] as $name => $index) {
                $columns = $this->indexColumns($class, $index);
                foreach ($this->tables[$table]['indexes'] as $existing => $candidate) {
                    if ([] === $candidate['flags'] && self::isFulfilledBy($candidate, ['columns' => $columns, 'unique' => true, 'primary' => false])) {
                        unset($this->tables[$table]['indexes'][$existing], $this->tables[$table]['implicit'][$existing]);
                        break;
                    }
                }
                $this->addIndex($table, \is_int($name) ? null : $name, $columns, true);
            }

            $processed[$class->name] = true;
        }

        foreach ($this->foreignKeys as $key => $foreignKey) {
            if (!isset($this->blacklisted[$key])) {
                $this->addForeignKey($foreignKey['table'], $foreignKey['foreignTable'], $foreignKey['columns'], $foreignKey['foreignColumns'], $foreignKey['onDelete'], null);
            }
        }

        $tables = [];
        foreach ($this->tables as $name => $table) {
            $indexes = [];
            $primaryKey = [];
            foreach ($table['indexes'] as $indexName => $index) {
                if ($index['primary']) {
                    $primaryKey = $index['columns'];
                } else {
                    $indexes[$indexName] = new Index($indexName, $index['columns'], $index['unique'], $index['flags']);
                }
            }
            ksort($indexes);
            $foreignKeys = [];
            foreach ($table['foreignKeys'] as $foreignKeyName => $foreignKey) {
                $foreignKeys[$foreignKeyName] = new ForeignKey($foreignKeyName, $foreignKey['columns'], $foreignKey['foreignTable'], $foreignKey['foreignColumns'], null === $foreignKey['onDelete'] ? null : strtoupper($foreignKey['onDelete']));
            }
            ksort($foreignKeys);
            $tables[$name] = new Table($name, array_map(static fn (array $column) => new Column(...$column), $table['columns']), $primaryKey, $indexes, $foreignKeys);
        }
        ksort($tables);

        return $tables;
    }

    private function create(string $table): void
    {
        $this->tables[$table] ??= ['name' => $table, 'columns' => [], 'indexes' => [], 'implicit' => [], 'foreignKeys' => []];
    }

    private function columns(Draft $class, string $table): void
    {
        foreach ($class->fieldMappings as $mapping) {
            if ($class->isInheritanceTypeSingleTable() && null !== $mapping['inherited']) {
                continue;
            }
            $this->column($class, $mapping, $table);
        }
    }

    /** SchemaTool::gatherColumn(): a field's column, its unique index or index. */
    private function column(Draft $class, array $mapping, string $table): void
    {
        $nullable = (bool) $mapping['nullable'];
        if ($class->isInheritanceTypeSingleTable() && $class->parentClasses) {
            $nullable = true;
        }

        $length = $mapping['length'];
        if ('string' === strtolower($mapping['type']) && null === $length) {
            $length = 255;
        }

        $options = $mapping['options'] ?? [];
        $autoincrement = 'identity' === $class->generatorType && $class->identifier === [$mapping['fieldName']];
        if ($class->isInheritanceTypeJoined() && !$class->isRootEntity()) {
            $autoincrement = false;
        }

        $this->tables[$table]['columns'][$mapping['columnName']] = self::definition(
            $mapping['columnName'],
            $mapping['type'],
            $nullable,
            $length,
            $mapping['precision'],
            $mapping['scale'],
            $options,
            $autoincrement,
        );

        if ($mapping['unique']) {
            $this->addIndex($table, null, [$mapping['columnName']], true);
        }
        if ($mapping['index']) {
            $this->addIndex($table, null, [$mapping['columnName']], false);
        }
    }

    /** @param array<string, mixed> $options */
    private static function definition(string $name, string $type, bool $nullable, ?int $length, ?int $precision, ?int $scale, array $options, bool $autoincrement): array
    {
        $default = $options['default'] ?? null;
        if ($default instanceof \BackedEnum) {
            $default = $default->value;
        }

        return [
            'name' => $name,
            'type' => $type,
            'nullable' => $nullable,
            'length' => $length,
            'precision' => $precision,
            'scale' => null === $precision && 0 === $scale ? null : $scale,
            'fixed' => (bool) ($options['fixed'] ?? false),
            'unsigned' => (bool) ($options['unsigned'] ?? false),
            'autoincrement' => $autoincrement,
            'default' => \is_scalar($default) ? $default : null,
            'values' => null,
        ];
    }

    private function discriminator(Draft $class, string $table): void
    {
        $column = $class->discriminatorColumn;
        $length = $column['length'] ?? null;
        if ('string' === strtolower($column['type']) && null === $length) {
            $length = 255;
        }
        $this->tables[$table]['columns'][$column['name']] = self::definition($column['name'], $column['type'], false, $length, null, null, [], false);
    }

    /** SchemaTool::gatherRelationsSql(): an owning to-one relation's join columns, an owning many-to-many's join table. */
    private function relations(Draft $class, string $table): void
    {
        foreach ($class->associationMappings as $property => $mapping) {
            if (null !== $mapping['inherited'] && !\in_array($property, $class->identifier, true)) {
                continue;
            }

            $target = $this->builder->draft($mapping['targetEntity']);
            if (\in_array($mapping['type'], ['one_to_one', 'many_to_one'], true) && $mapping['isOwningSide']) {
                $this->joinColumns($mapping['joinColumns'], $table, $target);
            } elseif ('many_to_many' === $mapping['type'] && $mapping['isOwningSide']) {
                $joinTable = $mapping['joinTable']['name'];
                $this->create($joinTable);
                $primaryKey = array_merge(
                    $this->joinColumns($mapping['joinTable']['joinColumns'], $joinTable, $class),
                    $this->joinColumns($mapping['joinTable']['inverseJoinColumns'], $joinTable, $target),
                );
                $this->addIndex($joinTable, 'PRIMARY', $primaryKey, true, true);
            }
        }
    }

    /**
     * SchemaTool::gatherRelationJoinColumns(): each join column, of its referenced column's type; a unique index
     * for a unique one; the foreign key kept for the end.
     *
     * @param list<array<string, mixed>> $joinColumns
     *
     * @return list<string> the columns
     */
    private function joinColumns(array $joinColumns, string $table, Draft $target): array
    {
        $local = [];
        $foreign = [];
        $onDelete = null;
        $unique = [];
        foreach ($joinColumns as $joinColumn) {
            [$defining, $field] = $this->definingClass($target, $joinColumn['referencedColumnName']) ?? throw new \LogicException(\sprintf('No column "%s" in %s.', $joinColumn['referencedColumnName'], $target->name));
            $local[] = $joinColumn['name'];
            $foreign[] = $joinColumn['referencedColumnName'];

            if (!isset($this->tables[$table]['columns'][$joinColumn['name']])) {
                $mapping = $defining->fieldMappings[$field];
                $this->tables[$table]['columns'][$joinColumn['name']] = self::definition(
                    $joinColumn['name'],
                    $mapping['type'],
                    (bool) ($joinColumn['nullable'] ?? true),
                    $mapping['length'],
                    'decimal' === $mapping['type'] ? $mapping['precision'] : null,
                    'decimal' === $mapping['type'] ? $mapping['scale'] : null,
                    $mapping['options'] ?? [],
                    false,
                );
            }

            if (true === $joinColumn['unique']) {
                $unique[] = [$joinColumn['name']];
            }
            if (isset($joinColumn['onDelete'])) {
                $onDelete = $joinColumn['onDelete'];
            }
        }

        foreach ($unique as $columns) {
            $this->addIndex($table, null, $columns, true);
        }

        $key = $table.'.'.implode('', $local);
        $foreignTable = $target->table['name'];
        if (isset($this->foreignKeys[$key])) {
            $existing = $this->foreignKeys[$key];
            if ($existing['foreignTable'] === $foreignTable && !array_diff($foreign, $existing['foreignColumns']) && !array_diff($existing['foreignColumns'], $foreign)) {
                return $local;
            }
            if (!isset($this->blacklisted[$key])) {
                $this->addIndex($table, null, $local, false);
            }
            $this->blacklisted[$key] = true;
        } elseif (!isset($this->blacklisted[$key])) {
            $this->foreignKeys[$key] = ['table' => $table, 'foreignTable' => $foreignTable, 'columns' => $local, 'foreignColumns' => $foreign, 'onDelete' => $onDelete];
        }

        return $local;
    }

    /** @return array{Draft, string}|null the class that maps the referenced column, and its field */
    private function definingClass(Draft $class, string $column): ?array
    {
        $field = $class->fieldNames[$column] ?? $column;
        if (isset($class->fieldMappings[$field])) {
            return [$class, $field];
        }

        foreach ($class->identifier as $property) {
            $mapping = $class->associationMappings[$property] ?? null;
            if (null !== $mapping && 1 === \count($mapping['joinColumns']) && $mapping['joinColumns'][0]['name'] === $column) {
                return $this->definingClass($this->builder->draft($mapping['targetEntity']), $mapping['joinColumns'][0]['referencedColumnName']);
            }
        }

        return null;
    }

    /** @return list<string> an index's columns: its columns, or its fields' columns */
    private function indexColumns(Draft $class, array $index): array
    {
        if (isset($index['columns'])) {
            return array_values($index['columns']);
        }

        $columns = [];
        foreach ($index['fields'] ?? [] as $field) {
            if (isset($class->fieldMappings[$field])) {
                $columns[] = $class->fieldMappings[$field]['columnName'];
            } elseif (isset($class->associationMappings[$field])) {
                foreach ($class->associationMappings[$field]['joinColumns'] as $joinColumn) {
                    $columns[] = $joinColumn['name'];
                }
            }
        }

        return $columns;
    }

    /** DBAL's Table::_addIndex(): the implicit indexes the new one fulfils give way to it. @param list<string> $columns */
    private function addIndex(string $table, ?string $name, array $columns, bool $unique, bool $primary = false, array $flags = []): void
    {
        $name ??= $this->naming->generatedName($table, $columns, $unique ? 'uniq' : 'idx');
        $index = ['columns' => $columns, 'unique' => $unique || $primary, 'primary' => $primary, 'flags' => array_values(array_map('strtolower', $flags))];

        foreach ($this->tables[$table]['implicit'] as $implicit => $_) {
            if (isset($this->tables[$table]['indexes'][$implicit]) && self::isFulfilledBy($this->tables[$table]['indexes'][$implicit], $index)) {
                unset($this->tables[$table]['indexes'][$implicit], $this->tables[$table]['implicit'][$implicit]);
            }
        }

        $this->tables[$table]['indexes'][$name] = $index;
    }

    /** DBAL's Table::_addForeignKeyConstraint(): the key, and an index of its columns unless one already backs it. */
    private function addForeignKey(string $table, string $foreignTable, array $columns, array $foreignColumns, ?string $onDelete, ?string $name): void
    {
        $name ??= $this->naming->generatedName($table, $columns, 'fk');
        $this->tables[$table]['foreignKeys'][$name] = ['columns' => $columns, 'foreignTable' => $foreignTable, 'foreignColumns' => $foreignColumns, 'onDelete' => $onDelete];

        $candidate = ['columns' => $columns, 'unique' => false, 'primary' => false];
        foreach ($this->tables[$table]['indexes'] as $index) {
            if (self::isFulfilledBy($candidate, $index)) {
                return;
            }
        }

        $indexName = $this->naming->generatedName($table, $columns, 'idx');
        $this->addIndex($table, $indexName, $columns, false);
        $this->tables[$table]['implicit'][$indexName] = true;
    }

    /** DBAL's Index::isFulfilledBy(): the other index has the same columns in the same order, and constrains at least as much. */
    private static function isFulfilledBy(array $index, array $other): bool
    {
        if (\count($other['columns']) !== \count($index['columns']) || !self::spans($index['columns'], $other['columns'])) {
            return false;
        }

        if (!$index['unique'] && !$index['primary']) {
            return true;
        }

        return $other['primary'] === $index['primary'] && $other['unique'] === $index['unique'];
    }

    /** DBAL's Index::spansColumns(): each of these columns, in order, is the other list's at the same place. */
    private static function spans(array $columns, array $other): bool
    {
        foreach (array_values($columns) as $i => $column) {
            if (!isset($other[$i]) || strtolower($column) !== strtolower($other[$i])) {
                return false;
            }
        }

        return true;
    }
}
