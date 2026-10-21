<?php

namespace Omnibridge\Schema;

/** A column holding the identifier of the related entity: its name, the column of the other table it references. */
final class JoinColumn
{
    use Exports;

    public function __construct(
        public readonly string $name,
        public readonly string $referencedColumn = 'id',
        public readonly bool $nullable = true,
        public readonly bool $unique = false,
        public readonly ?string $onDelete = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
