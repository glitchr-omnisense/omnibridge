<?php

namespace Omnibridge\Schema\Declaration;

/**
 * One mapping attribute as an entity carries it, read without its class: its
 * name in the vocabulary (Column, ManyToOne, JoinColumn...) and its arguments
 * by parameter name, as written - a positional argument named after the
 * parameter it fills, a nested attribute (a JoinTable's join columns) an
 * Attribute itself. get() adds the vocabulary's default of an argument left out.
 */
final class Attribute
{
    /** @param array<string, mixed> $arguments as written, by parameter name */
    public function __construct(
        public readonly string $name,
        public readonly array $arguments = [],
    ) {
    }

    public function has(string $argument): bool
    {
        return \array_key_exists($argument, $this->arguments);
    }

    /** The argument as written, else the vocabulary's default. */
    public function get(string $argument): mixed
    {
        return \array_key_exists($argument, $this->arguments) ? $this->arguments[$argument] : Vocabulary::defaultOf($this->name, $argument);
    }

    /** @return array<string, mixed> every parameter of the attribute: as written, else its default */
    public function all(): array
    {
        return $this->arguments + Vocabulary::defaults($this->name);
    }

    /** @return array{name: string, arguments?: array<string, mixed>} */
    public function toArray(): array
    {
        $arguments = array_map(static fn (mixed $value) => self::export($value), $this->arguments);

        return ['name' => $this->name] + ($arguments ? ['arguments' => $arguments] : []);
    }

    /** @param array{name: string, arguments?: array<string, mixed>} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['name'], array_map(self::import(...), $data['arguments'] ?? []));
    }

    private static function export(mixed $value): mixed
    {
        if ($value instanceof self) {
            return ['@attribute' => $value->toArray()];
        }

        return \is_array($value) ? array_map(self::export(...), $value) : $value;
    }

    private static function import(mixed $value): mixed
    {
        if (\is_array($value) && isset($value['@attribute']) && 1 === \count($value)) {
            return self::fromArray($value['@attribute']);
        }

        return \is_array($value) ? array_map(self::import(...), $value) : $value;
    }
}
