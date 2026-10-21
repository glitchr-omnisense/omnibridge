<?php

namespace Omnibridge\Schema;

/** An index of a table, unique or not, over its columns in order; flags such as fulltext. */
final class Index
{
    use Exports;

    /**
     * @param list<string> $columns
     * @param list<string> $flags
     */
    public function __construct(
        public readonly string $name,
        public readonly array $columns,
        public readonly bool $unique = false,
        public readonly array $flags = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
