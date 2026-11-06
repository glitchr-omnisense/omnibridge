<?php

namespace Omnibridge\Container;

/**
 * What a host discovers among the services declared as data (Container\Declarations), declared as data too: the
 * registries omnisense/omnibase's Base\Discovery gives (plan "omnibase agnostic", step F5e slice 4c), read the way every
 * host reads them - omnibridge/symfony compiles them in one compiler pass, omnibridge/laravel applies them to what it
 * binds, Omnibridge\Container builds with them. Four kinds are generic, and read here:
 *
 * - `autoconfigure`: a list of `['instanceof' => class, 'tags' => [tag, ...], 'requires' => [class, ...]]` - a service
 *   whose definition is autoconfigured (`autoconfigure: true`, its own or its parent's) and whose class is, extends or
 *   implements `instanceof` gets these tags, after its own (Symfony's registerForAutoconfiguration); `requires`: the
 *   entry holds only where those classes are installed;
 * - `collect`: a list of `['service' => id, 'method' => name, 'tag' => tag]` (or `'instanceof' => class` in place of
 *   the tag: every defined service whose id is a class that is one) - each service the tag names, in its order, handed
 *   to the collecting service by that method (`$service->method($tagged)`), where the collecting service is defined;
 * - `fallbacks`: `id => ['candidates' => [id, ...], 'default' => class]` - a service no one binds is the first
 *   candidate defined, else the default class;
 * - `preferred`: `alias => ['prefer' => id, 'over' => id]` - an alias to `over` is moved to `prefer` where `prefer` is
 *   defined (a shared pool over the application's).
 *
 * The other kinds of a registry (omnibase's contracts, enumerations, repositories, storages...) are a host's to
 * compile; nothing here reads them. No class is loaded but those a definition names, to answer `instanceof`.
 */
final class Discovery
{
    public const GENERIC = ['autoconfigure', 'collect', 'fallbacks', 'preferred'];

    /**
     * The tags autoconfiguration gives a class, in the registry's order.
     *
     * @param list<array{instanceof: string, tags: list<string|array<string, mixed>>, requires?: list<string>}> $autoconfigure
     *
     * @return list<string|array<string, mixed>>
     */
    public static function tags(array $autoconfigure, string $class): array
    {
        if (!Declarations::loadable($class)) {
            return [];
        }
        $tags = [];
        foreach ($autoconfigure as $entry) {
            if (!Declarations::applies($entry) || !is_a($class, $entry['instanceof'], true)) {
                continue;
            }
            array_push($tags, ...$entry['tags']);
        }

        return $tags;
    }

    /**
     * The resolved definitions (Declarations::resolve()) with the tags autoconfiguration gives the autoconfigured ones
     * added after their own.
     *
     * @param array<string, array<string, mixed>> $resolved
     * @param list<array<string, mixed>>          $autoconfigure
     *
     * @return array<string, array<string, mixed>>
     */
    public static function autoconfigure(array $resolved, array $autoconfigure): array
    {
        foreach ($resolved as $id => $definition) {
            if (empty($definition['autoconfigure'])) {
                continue;
            }
            $tags = self::tags($autoconfigure, $definition['class']);
            if ([] !== $tags) {
                $resolved[$id]['tags'] = [...$definition['tags'], ...$tags];
            }
        }

        return $resolved;
    }

    /**
     * The services each collection hands to its collecting service, in their order: id => list of [method, tagged id].
     * Only the collecting services `$defined` answers for are listed.
     *
     * @param array<string, array<string, mixed>> $resolved with their tags (autoconfigure() applied)
     * @param list<array<string, mixed>>          $collect
     * @param (\Closure(string $id): bool)|null   $defined  whether the host defines a service (the resolved ids by default)
     *
     * @return array<string, list<array{0: string, 1: string}>>
     */
    public static function collected(array $resolved, array $collect, ?\Closure $defined = null): array
    {
        $defined ??= static fn (string $id): bool => isset($resolved[$id]);
        $calls = [];
        foreach ($collect as $collection) {
            if (!$defined($collection['service'])) {
                continue;
            }
            if (isset($collection['tag'])) {
                $ids = Declarations::tagged($resolved, $collection['tag']);
            } else {
                $ids = array_values(array_filter(array_keys($resolved), static fn (string $id): bool => str_contains($id, '\\') && Declarations::loadable($id) && is_subclass_of($id, $collection['instanceof'])));
            }
            foreach ($ids as $id) {
                $calls[$collection['service']][] = [$collection['method'], $id];
            }
        }

        return $calls;
    }

    /**
     * What a fallback binds: the first candidate defined, else its default class.
     *
     * @param array{candidates?: list<string>, default: string} $fallback
     * @param \Closure(string $id): bool                         $defined
     *
     * @return array{alias: string}|array{class: string}
     */
    public static function fallback(array $fallback, \Closure $defined): array
    {
        foreach ($fallback['candidates'] ?? [] as $candidate) {
            if ($defined($candidate)) {
                return ['alias' => $candidate];
            }
        }

        return ['class' => $fallback['default']];
    }

    /**
     * Each service's tags of one name with their attributes, the autoconfigured ones included: id => list of attributes
     * (what an event-listener tag's `event` says, for a host that registers the listeners itself).
     *
     * @param array<string, array<string, mixed>> $resolved with their tags (autoconfigure() applied)
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function attributes(array $resolved, string $tag): array
    {
        $found = [];
        foreach ($resolved as $id => $definition) {
            foreach ($definition['tags'] as $item) {
                if ((\is_string($item) ? $item : ($item['name'] ?? null)) === $tag) {
                    $found[$id][] = \is_string($item) ? [] : array_diff_key($item, ['name' => true]);
                }
            }
        }

        return $found;
    }
}
