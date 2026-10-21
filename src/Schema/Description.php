<?php

namespace Omnibridge\Schema;

/**
 * The structure of an application's data, described once and outside any
 * ORM (Marco's bridge principle, 2026-10-19: several persistence techniques,
 * the same tables, the same columns): its entities - each class, its table,
 * its fields and relations, its inheritance - and the tables they make, with
 * their columns, keys, indexes and foreign keys.
 *
 * A persistence bridge reads it: omnibridge/doctrine builds Doctrine's
 * metadata from the declarations it comes from and gives it back from an
 * entity manager; another persistence (Eloquent...) maps the same tables with
 * it. toArray() is its plain form, a JSON file's; fromArray() reads it back.
 */
final class Description
{
    use Exports;

    /**
     * @param array<string, Entity> $entities by class
     * @param array<string, Table>  $tables   by name
     */
    public function __construct(
        public readonly array $entities = [],
        public readonly array $tables = [],
    ) {
    }

    /** @throws \OutOfBoundsException for a class the description does not hold */
    public function entity(string $class): Entity
    {
        return $this->entities[$class] ?? throw new \OutOfBoundsException(\sprintf('No entity %s in the description.', $class));
    }

    /** @throws \OutOfBoundsException for a table the description does not hold */
    public function table(string $name): Table
    {
        return $this->tables[$name] ?? throw new \OutOfBoundsException(\sprintf('No table %s in the description.', $name));
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            array_map(Entity::fromArray(...), $data['entities'] ?? []),
            array_map(Table::fromArray(...), $data['tables'] ?? []),
        );
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR)."\n";
    }

    public static function fromJson(string $json): self
    {
        return self::fromArray(json_decode($json, true, 512, \JSON_THROW_ON_ERROR));
    }
}
