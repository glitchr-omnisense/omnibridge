<?php

namespace Omnibridge\Schema;

/** The table between two entities of a many-to-many relation: its columns toward the owner, then toward the target. */
final class JoinTable
{
    use Exports;

    /**
     * @param list<JoinColumn> $joinColumns        toward the entity that owns the relation
     * @param list<JoinColumn> $inverseJoinColumns toward its target
     */
    public function __construct(
        public readonly string $name,
        public readonly array $joinColumns = [],
        public readonly array $inverseJoinColumns = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'],
            array_map(JoinColumn::fromArray(...), $data['joinColumns'] ?? []),
            array_map(JoinColumn::fromArray(...), $data['inverseJoinColumns'] ?? []),
        );
    }
}
