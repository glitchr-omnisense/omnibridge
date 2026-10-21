<?php

namespace Omnibridge\Schema;

/**
 * An entity class and how it is stored: its table, the entity it inherits
 * from and the root of its hierarchy, how the hierarchy is stored, its
 * identifier, its fields and its relations, by property (the inherited ones
 * among them). A mapped superclass or an embeddable has no table of its own
 * (kind), its fields are copied into the entities that extend or embed it.
 */
final class Entity
{
    public const ENTITY = 'entity';
    public const MAPPED_SUPERCLASS = 'mapped_superclass';
    public const EMBEDDABLE = 'embeddable';

    use Exports;

    /**
     * @param list<string>            $identifier the properties
     * @param array<string, Field>    $fields     by property
     * @param array<string, Relation> $relations  by property
     * @param list<string>            $subclasses the entities below it in its hierarchy
     */
    public function __construct(
        public readonly string $class,
        public readonly ?string $table,
        public readonly string $kind = self::ENTITY,
        public readonly ?string $parent = null,
        public readonly ?string $root = null,
        public readonly ?Inheritance $inheritance = null,
        public readonly array $identifier = [],
        public readonly array $fields = [],
        public readonly array $relations = [],
        public readonly array $subclasses = [],
    ) {
    }

    /** @throws \OutOfBoundsException for a property that is no field */
    public function field(string $property): Field
    {
        return $this->fields[$property] ?? throw new \OutOfBoundsException(\sprintf('No field %s::$%s.', $this->class, $property));
    }

    /** @throws \OutOfBoundsException for a property that is no relation */
    public function relation(string $property): Relation
    {
        return $this->relations[$property] ?? throw new \OutOfBoundsException(\sprintf('No relation %s::$%s.', $this->class, $property));
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $data['inheritance'] = isset($data['inheritance']) ? Inheritance::fromArray($data['inheritance']) : null;
        $data['fields'] = array_map(Field::fromArray(...), $data['fields'] ?? []);
        $data['relations'] = array_map(Relation::fromArray(...), $data['relations'] ?? []);
        $data += ['table' => null];

        return new self(...$data);
    }
}
