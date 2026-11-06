<?php

namespace Omnibridge\Options;

/**
 * A configuration checked against a schema declared as data, without a framework: what a host that has no
 * Symfony Config component validates its options with (omnibridge/laravel's config, a plain-PHP host), and what
 * omnibridge/symfony compiles into a TreeBuilder from the same schema (Options\SchemaTree there). Both answer the
 * same: the same values normalised, merged and completed with the same defaults, the same options refused with
 * the same message (InvalidOption), the same deprecations.
 *
 * A schema is the root's children, each a node:
 * - `type`: `bool`, `int`, `float`, `scalar` (a string, a number, a boolean or null), `enum` (one of `values`),
 *   `mixed` (anything), or `array`;
 * - `info`: what the option is for (a refused value's hint);
 * - `default`: its value when the configuration says nothing (a node without one is left out); a `bool` whose
 *   default is null takes null, any other takes null as true (Symfony's booleanNode);
 * - `deprecated`: `['package' => ..., 'since' => ..., 'message' => ...]`, `%node%` and `%path%` replaced, raised
 *   (E_USER_DEPRECATED, "Since <package> <since>: <message>") when the configuration sets it;
 * - `min`, `max` (`int`, `float`);
 * - an `array` is either a set of named `children`, completed with their defaults when `defaults` is true (absent
 *   otherwise), or a list or map of one `prototype`: `key` names the attribute an item's key is read from,
 *   `default_children` gives one default item when none is set, `default` the array when nothing is set;
 *   `deep_merging` false lets a later configuration replace it whole. Keys with a dash read as with an underscore.
 *
 * Several configurations (Symfony's when@env, imports) are merged in their order, the later over the earlier.
 */
final class Processor
{
    public const TYPES = ['bool', 'int', 'float', 'scalar', 'enum', 'mixed', 'array'];

    /**
     * The configurations merged and completed.
     *
     * @param array<string, array<string, mixed>> $schema  the root's children
     * @param list<array<array-key, mixed>|null>  $configs
     *
     * @return array<string, mixed>
     *
     * @throws InvalidOption
     */
    public static function process(array $schema, string $root, array $configs): array
    {
        $node = ['type' => 'array', 'children' => $schema];
        $current = [];
        foreach ($configs as $config) {
            $current = self::merge($node, $current, self::normalize($node, $config, $root), $root);
        }

        return self::finalize($node, $current, $root);
    }

    /**
     * What a configuration that says nothing gives: process() of no configuration.
     *
     * @param array<string, array<string, mixed>> $schema
     *
     * @return array<string, mixed>
     */
    public static function defaults(array $schema, string $root): array
    {
        return self::process($schema, $root, []);
    }

    /** @param array<string, mixed> $node */
    private static function normalize(array $node, mixed $value, string $path): mixed
    {
        if ('array' === $node['type'] && \is_array($value)) {
            $keys = [];
            foreach ($value as $key => $item) {
                $keys[\is_string($key) && str_contains($key, '-') && !str_contains($key, '_') && !\array_key_exists(str_replace('-', '_', $key), $value) ? str_replace('-', '_', $key) : $key] = $item;
            }
            $value = $keys;
        }
        $value = self::equivalent($node, $value);
        self::validateType($node, $value, $path);
        if ('array' !== $node['type'] || false === $value) {
            return $value;
        }
        if (isset($node['prototype'])) {
            return self::normalizeItems($node, $value, $path);
        }

        $children = $node['children'] ?? [];
        $normalized = [];
        foreach ($value as $name => $item) {
            if (isset($children[$name])) {
                $normalized[$name] = self::normalize($children[$name], $item, $path.'.'.$name);
                unset($value[$name]);
            }
        }
        if ($value) {
            $proposals = array_keys($children);
            sort($proposals);
            $guesses = [];
            foreach (array_keys($value) as $subject) {
                $minimum = \INF;
                foreach ($proposals as $proposal) {
                    $distance = levenshtein((string) $subject, (string) $proposal);
                    if ($distance <= $minimum && $distance < 3) {
                        $guesses[$proposal] = $distance;
                        $minimum = $distance;
                    }
                }
            }
            $message = \sprintf('Unrecognized option%s "%s" under "%s"', 1 === \count($value) ? '' : 's', implode(', ', array_keys($value)), $path);
            if ($guesses) {
                asort($guesses);
                $message .= \sprintf('. Did you mean "%s"?', implode('", "', array_keys($guesses)));
            } else {
                $message .= \sprintf('. Available option%s %s "%s".', 1 === \count($proposals) ? '' : 's', 1 === \count($proposals) ? 'is' : 'are', implode('", "', $proposals));
            }

            throw new InvalidOption($message, $path);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed>     $node
     * @param array<array-key, mixed>  $value
     *
     * @return array<array-key, mixed>
     */
    private static function normalizeItems(array $node, array $value, string $path): array
    {
        $key = $node['key'] ?? null;
        $isList = array_is_list($value);
        $normalized = [];
        foreach ($value as $index => $item) {
            if (null !== $key && \is_array($item)) {
                if (!isset($item[$key]) && \is_int($index) && $isList) {
                    throw new InvalidOption(\sprintf('The attribute "%s" must be set for path "%s".', $key, $path), $path);
                }
                if (isset($item[$key])) {
                    $index = $item[$key];
                    if (\is_float($index)) {
                        $index = var_export($index, true);
                    }
                    unset($item[$key]);
                    if (array_keys($item) === ['value']) {
                        $item = $item['value'];
                    }
                }
                if (\array_key_exists($index, $normalized)) {
                    throw new InvalidOption(\sprintf('Duplicate key "%s" for path "%s".', $index, $path), $path);
                }
            }
            $item = self::normalize($node['prototype'], $item, $path.'.'.$index);
            if (null !== $key || !$isList) {
                $normalized[$index] = $item;
            } else {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function merge(array $node, mixed $left, mixed $right, string $path): mixed
    {
        self::validateType($node, $left, $path);
        self::validateType($node, $right, $path);
        if ('array' !== $node['type']) {
            return $right;
        }
        if (false === $right) {
            return false;
        }
        if (false === $left || false === ($node['deep_merging'] ?? true)) {
            return $right;
        }

        if (isset($node['prototype'])) {
            $key = $node['key'] ?? null;
            $isList = array_is_list($right);
            foreach ($right as $index => $item) {
                if (null === $key && $isList) {
                    $left[] = $item;
                    continue;
                }
                if (!\array_key_exists($index, $left)) {
                    $left[$index] = \is_array($item) && 'array' === $node['prototype']['type'] ? self::merge($node['prototype'], [], $item, $path.'.'.$index) : $item;
                    continue;
                }
                $left[$index] = self::merge($node['prototype'], $left[$index], $item, $path.'.'.$index);
            }

            return $left;
        }

        $children = $node['children'] ?? [];
        foreach ($right as $name => $item) {
            if (!\array_key_exists($name, $left)) {
                $left[$name] = \is_array($item) && 'array' === ($children[$name]['type'] ?? null) ? self::merge($children[$name], [], $item, $path.'.'.$name) : $item;
                continue;
            }
            $left[$name] = self::merge($children[$name], $left[$name], $item, $path.'.'.$name);
        }

        return $left;
    }

    /** @param array<string, mixed> $node */
    private static function finalize(array $node, mixed $value, string $path): mixed
    {
        self::validateType($node, $value, $path);
        switch ($node['type']) {
            case 'array':
                if (isset($node['prototype'])) {
                    foreach ($value as $index => $item) {
                        $value[$index] = self::finalize($node['prototype'], $item, $path.'.'.$index);
                    }

                    return $value;
                }
                foreach ($node['children'] ?? [] as $name => $child) {
                    if (!\array_key_exists($name, $value)) {
                        if (self::hasDefault($child)) {
                            $value[$name] = self::default($child);
                        }
                        continue;
                    }
                    if (isset($child['deprecated'])) {
                        $deprecated = $child['deprecated'];
                        $message = strtr($deprecated['message'], ['%node%' => $name, '%path%' => $path]);
                        @trigger_error(($deprecated['package'] || $deprecated['since'] ? \sprintf('Since %s %s: ', $deprecated['package'], $deprecated['since']) : '').$message, \E_USER_DEPRECATED);
                    }
                    $value[$name] = self::finalize($child, $value[$name], $path.'.'.$name);
                }

                return $value;
            case 'int':
            case 'float':
                if (isset($node['min']) && $value < $node['min']) {
                    throw new InvalidOption(\sprintf('The value %s is too small for path "%s". Should be greater than or equal to %s', $value, $path, $node['min']), $path);
                }
                if (isset($node['max']) && $value > $node['max']) {
                    throw new InvalidOption(\sprintf('The value %s is too big for path "%s". Should be less than or equal to %s', $value, $path, $node['max']), $path);
                }

                return $value;
            case 'enum':
                if (!$value instanceof \UnitEnum && !\in_array($value, $node['values'], true)) {
                    $shown = match (true) {
                        \is_int($value) => $value,
                        \is_string($value) => \sprintf('"%s"', $value),
                        default => \sprintf('of type "%s"', get_debug_type($value)),
                    };
                    $permitted = implode(', ', array_unique(array_map(static fn (mixed $each): string => $each instanceof \UnitEnum ? ltrim(var_export($each, true), '\\') : (string) json_encode($each, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION), $node['values'])));

                    throw new InvalidOption(\sprintf('The value %s is not allowed for path "%s". Permissible values: %s.', $shown, $path, $permitted), $path);
                }

                return $value;
            default:
                return $value;
        }
    }

    /**
     * Whether a node has a value when the configuration says nothing: a set of children with `defaults`, any list
     * or map of a prototype, a leaf with a `default`.
     *
     * @param array<string, mixed> $node
     */
    public static function hasDefault(array $node): bool
    {
        if ('array' === $node['type']) {
            return isset($node['prototype']) || true === ($node['defaults'] ?? false);
        }

        return \array_key_exists('default', $node);
    }

    /**
     * A node's value when the configuration says nothing (hasDefault() first).
     *
     * @param array<string, mixed> $node
     */
    public static function default(array $node): mixed
    {
        if ('array' !== $node['type']) {
            return $node['default'] ?? null;
        }
        if (isset($node['prototype'])) {
            if (true === ($node['default_children'] ?? false)) {
                $item = self::hasDefault($node['prototype']) ? self::default($node['prototype']) : [];

                return [null === ($node['key'] ?? null) ? 0 : 'defaults' => $item];
            }

            return $node['default'] ?? [];
        }
        $defaults = [];
        foreach ($node['children'] ?? [] as $name => $child) {
            if (self::hasDefault($child)) {
                $defaults[$name] = self::default($child);
            }
        }

        return $defaults;
    }

    /**
     * What a value means before its type is checked: null and true are an empty array for an array, null is true
     * for a boolean that has a default but null.
     *
     * @param array<string, mixed> $node
     */
    private static function equivalent(array $node, mixed $value): mixed
    {
        if ('array' === $node['type']) {
            return null === $value || true === $value ? [] : $value;
        }
        if ('bool' === $node['type'] && null === $value && !self::nullable($node)) {
            return true;
        }

        return $value;
    }

    /** @param array<string, mixed> $node */
    private static function nullable(array $node): bool
    {
        return \array_key_exists('default', $node) && null === $node['default'];
    }

    /** @param array<string, mixed> $node */
    private static function validateType(array $node, mixed $value, string $path): void
    {
        $expected = match ($node['type']) {
            'array' => \is_array($value) ? null : 'array',
            'bool' => \is_bool($value) || (null === $value && self::nullable($node)) ? null : (self::nullable($node) ? 'bool" or "null' : 'bool'),
            'int' => \is_int($value) ? null : 'int',
            'float' => \is_int($value) || \is_float($value) ? null : 'float',
            'scalar', 'enum' => \is_scalar($value) || null === $value || ('enum' === $node['type'] && $value instanceof \UnitEnum) ? null : 'scalar',
            'mixed' => null,
            default => throw new \LogicException(\sprintf('Unknown option type "%s" at path "%s" (one of %s).', $node['type'], $path, implode(', ', self::TYPES))),
        };
        if (null === $expected) {
            return;
        }
        // Symfony's array node ends its message without a period.
        $message = \sprintf('Invalid type for path "%s". Expected "%s", but got "%s"', $path, $expected, get_debug_type($value)).('array' === $node['type'] ? '' : '.');
        if (isset($node['info']) && '' !== $node['info']) {
            $message .= "\nHint: ".$node['info'];
        }

        throw new InvalidOption($message, $path);
    }
}
