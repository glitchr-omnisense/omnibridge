<?php

namespace Omnibridge\Schema;

/**
 * A property of an entity that holds other entities: its kind, its target,
 * the property of the other side (mappedBy on the inverse side, inversedBy on
 * the owning side), and on the owning side where the link is kept - the join
 * columns of a to-one relation, the join table of a many-to-many one. Then
 * how a collection is ordered and keyed, whether the relation is part of the
 * identifier, where it comes from (as a Field).
 */
final class Relation
{
    public const ONE_TO_ONE = 'one_to_one';
    public const MANY_TO_ONE = 'many_to_one';
    public const ONE_TO_MANY = 'one_to_many';
    public const MANY_TO_MANY = 'many_to_many';

    use Exports;

    /**
     * @param list<JoinColumn>      $joinColumns
     * @param array<string, string> $orderBy     property => ASC|DESC
     */
    public function __construct(
        public readonly string $property,
        public readonly string $kind,
        public readonly string $target,
        public readonly bool $owning,
        public readonly ?string $mappedBy = null,
        public readonly ?string $inversedBy = null,
        public readonly array $joinColumns = [],
        public readonly ?JoinTable $joinTable = null,
        public readonly array $orderBy = [],
        public readonly ?string $indexBy = null,
        public readonly bool $id = false,
        public readonly ?string $inheritedFrom = null,
        public readonly ?string $declaredIn = null,
    ) {
    }

    public function isToOne(): bool
    {
        return self::ONE_TO_ONE === $this->kind || self::MANY_TO_ONE === $this->kind;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $data['joinColumns'] = array_map(JoinColumn::fromArray(...), $data['joinColumns'] ?? []);
        $data['joinTable'] = isset($data['joinTable']) ? JoinTable::fromArray($data['joinTable']) : null;

        return new self(...$data);
    }
}
