<?php

namespace Omnibridge\Schema;

/**
 * A column of a table, as the database holds it: its name, its type by the
 * portable name the persistence layers share (string, integer, datetime,
 * json... or an application's own type, thread_state), and what the type
 * leaves open - length, precision and scale, fixed width, unsigned, nullable,
 * default, auto-increment, the values of an enumeration or a set.
 */
final class Column
{
    use Exports;

    /** @param list<string>|null $values the values an enumeration or a set column admits, in the order its type gives them */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $nullable = false,
        public readonly ?int $length = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
        public readonly bool $fixed = false,
        public readonly bool $unsigned = false,
        public readonly bool $autoincrement = false,
        public readonly string|int|float|bool|null $default = null,
        public readonly ?array $values = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
