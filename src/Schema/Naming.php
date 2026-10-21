<?php

namespace Omnibridge\Schema;

/**
 * How the structure names what its declarations leave unnamed: a class's
 * table, a property's column, a relation's join columns and join table, an
 * index or a foreign key nobody named. Doctrine's naming strategy asks the
 * same questions (omnibridge/doctrine's NamingStrategy answers them with
 * this); another persistence asks them here, and finds the same tables.
 */
interface Naming
{
    public function classToTableName(string $className): string;

    public function propertyToColumnName(string $propertyName, string $className): string;

    public function embeddedFieldToColumnName(string $propertyName, string $embeddedColumnName, string $className, string $embeddedClassName): string;

    public function referenceColumnName(): string;

    public function joinColumnName(string $propertyName, string $className): string;

    public function joinTableName(string $sourceEntity, string $targetEntity, string $propertyName): string;

    public function joinKeyColumnName(string $entityName, ?string $referencedColumnName): string;

    /**
     * The name of an index (prefix idx), a unique index (uniq) or a foreign key (fk) its declaration does not
     * name, from its table and columns.
     *
     * @param list<string> $columns
     */
    public function generatedName(string $table, array $columns, string $prefix): string;
}
