<?php

namespace Omnibridge\Schema;

/**
 * The plain-data form of a description's parts (toArray()): each property by
 * its name, the nested parts as arrays; a value its constructor would give
 * anyway left out (a column not null, no length) - the form a JSON file holds,
 * which fromArray() reads back.
 *
 * @internal
 */
trait Exports
{
    /** @var array<class-string, array<string, mixed>> the constructor's defaults, by class */
    private static array $defaults = [];

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $defaults = self::$defaults[static::class] ??= self::defaults();

        $data = [];
        foreach (get_object_vars($this) as $name => $value) {
            if (\array_key_exists($name, $defaults) && $defaults[$name] === $value) {
                continue;
            }

            $data[$name] = self::export($value);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private static function defaults(): array
    {
        $defaults = [];
        foreach ((new \ReflectionMethod(static::class, '__construct'))->getParameters() as $parameter) {
            if ($parameter->isDefaultValueAvailable()) {
                $defaults[$parameter->getName()] = $parameter->getDefaultValue();
            }
        }

        return $defaults;
    }

    private static function export(mixed $value): mixed
    {
        if (\is_array($value)) {
            return array_map(self::export(...), $value);
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        return \is_object($value) && method_exists($value, 'toArray') ? $value->toArray() : $value;
    }
}
