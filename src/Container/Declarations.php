<?php

namespace Omnibridge\Container;

/**
 * Services declared as data (omnisense/omnibase's Base\Services, an application's own list), read the way every host
 * reads them: Omnibridge\Container in plain PHP, omnibridge/laravel's bindings. omnibridge/symfony compiles the same
 * data into Symfony's container itself, its `symfony` parts included.
 *
 * A definition: `class` (its parent's when left out), `id` (the class by default), `parent` (another definition of the
 * list: its class, arguments, bindings, calls and factory are inherited, the child's own given over them, as Symfony
 * resolves a child definition), `requires` (classes or interfaces that must exist), `environment` (the one it is
 * registered in), `abstract` (a parent only), `arguments` by position (0, 1, or `index_N` over a parent's) or by name
 * (`$name`), `bind` (values by parameter name for what the arguments leave out), `factory`, `calls`, `decorates`
 * (`id`, `on_invalid`: `ignore` drops the decoration of a service that is not there), `tags`, `contracts` (aliases: a
 * list item, or an alias's name => its options). What only Symfony reads is left out here (`symfony`, `public`,
 * `shared`, `lazy`, `autowire`, `autoconfigure`). The values' syntax is the containers' (Omnibridge\Container::value()).
 */
final class Declarations
{
    /**
     * Whether a host registers a definition: what it requires is installed and, where it names an environment, the
     * host runs that one (a host that does not know its environment's name passes null: such a definition is left out).
     *
     * @param array<string, mixed> $definition
     */
    public static function applies(array $definition, ?string $environment = null): bool
    {
        foreach ($definition['requires'] ?? [] as $type) {
            if (!class_exists($type) && !interface_exists($type)) {
                return false;
            }
        }

        return !isset($definition['environment']) || $definition['environment'] === $environment;
    }

    /** @param array<string, mixed> $definition its `id`, its class by default */
    public static function id(array $definition): string
    {
        return $definition['id'] ?? $definition['class'];
    }

    /**
     * A definition's contracts as alias => options (`public`, `target`): a list item is a private alias of the service.
     *
     * @param array<string, mixed> $definition
     *
     * @return array<string, array{public?: bool, target?: string}>
     */
    public static function contracts(array $definition): array
    {
        $contracts = [];
        foreach ($definition['contracts'] ?? [] as $alias => $options) {
            \is_int($alias) ? $contracts[$options] = [] : $contracts[$alias] = (array) $options;
        }

        return $contracts;
    }

    /** The position an argument's key gives (0, 1... or Symfony's `index_N` over a parent's), null for a name. */
    public static function position(int|string $key): ?int
    {
        if (\is_int($key)) {
            return $key;
        }

        return preg_match('/^index_(\d+)$/', $key, $match) ? (int) $match[1] : null;
    }

    /**
     * The definitions a host registers, by id in their order, each resolved: its parents' class, arguments (by position
     * as integers, by name as `$name`), bindings, calls and factory inherited; the abstract ones (parents only), those
     * whose requirements or environment do not hold, and those whose class is
     * nowhere (under a parent the list does not define) left out. No class is loaded here: a host asks whether a class
     * exists when the service is asked (loadable()).
     *
     * @param iterable<array<string, mixed>> $definitions
     *
     * @return array<string, array{class: class-string, arguments: array<int|string, mixed>, bind: array<string, mixed>, calls: list<array{0: string, 1?: array<mixed>, 2?: bool}>, factory: mixed, decorates: array<string, mixed>|null, tags: list<string|array<string, mixed>>, contracts: array<string, array<string, mixed>>, autoconfigure: bool}>
     */
    public static function resolve(iterable $definitions, ?string $environment = null): array
    {
        $all = [];
        foreach ($definitions as $definition) {
            if (self::applies($definition, $environment)) {
                $all[self::id($definition)] = $definition;
            }
        }

        $resolved = [];
        foreach ($all as $id => $definition) {
            if (!empty($definition['abstract'])) {
                continue;
            }
            $chain = [$definition];
            $seen = [$id => true];
            while (isset($definition['parent'], $all[$definition['parent']]) && !isset($seen[$definition['parent']])) {
                $seen[$definition['parent']] = true;
                $definition = $all[$definition['parent']];
                array_unshift($chain, $definition);
            }
            $class = null;
            $arguments = [];
            $bind = [];
            $calls = [];
            $factory = null;
            $autoconfigure = false;
            foreach ($chain as $link) {
                $class = $link['class'] ?? $class;
                $autoconfigure = (bool) ($link['autoconfigure'] ?? $autoconfigure);
                foreach ($link['arguments'] ?? [] as $key => $value) {
                    $position = self::position($key);
                    $arguments[$position ?? (str_starts_with((string) $key, '$') ? $key : '$'.$key)] = $value;
                }
                $bind = ($link['bind'] ?? []) + $bind;
                array_push($calls, ...$link['calls'] ?? []);
                $factory = $link['factory'] ?? $factory;
            }
            // A child that names no class is its id's when the id is a class (Symfony's ResolveClassPass), else its parent's.
            if (!isset($chain[\count($chain) - 1]['class']) && \count($chain) > 1 && str_contains($id, '\\') && self::loadable($id)) {
                $class = $id;
            }
            if (null === $class) {
                continue;
            }
            $resolved[$id] = [
                'class' => $class,
                'arguments' => $arguments,
                'bind' => $bind,
                'calls' => $calls,
                'factory' => $factory,
                'decorates' => $chain[\count($chain) - 1]['decorates'] ?? null,
                'tags' => $chain[\count($chain) - 1]['tags'] ?? [],
                'contracts' => self::contracts($chain[\count($chain) - 1]),
                // Autoconfigured (its own or its parent's, as Symfony resolves a child): what Discovery::autoconfigure() tags.
                'autoconfigure' => $autoconfigure,
            ];
        }

        return $resolved;
    }

    /** Whether a class can be loaded: it exists, and so do its parents (a class extending a package's that is not installed cannot). */
    public static function loadable(string $class): bool
    {
        try {
            return class_exists($class);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The ids of the services a tag names, in their order.
     *
     * @param array<string, array{tags: list<string|array<string, mixed>>}> $resolved
     *
     * @return list<string>
     */
    public static function tagged(array $resolved, string $tag): array
    {
        $ids = [];
        foreach ($resolved as $id => $definition) {
            foreach ($definition['tags'] as $item) {
                if ((\is_string($item) ? $item : ($item['name'] ?? null)) === $tag) {
                    $ids[] = $id;
                    break;
                }
            }
        }

        return $ids;
    }
}
