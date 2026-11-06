<?php

namespace Omnibridge;

use Omnibridge\Container\Declarations;
use Omnibridge\Container\Discovery;
use Omnibridge\Exception\ContainerException;
use Omnibridge\Exception\ServiceNotFoundException;
use Omnibridge\Persistence\Persistences;
use Psr\Container\ContainerInterface;

/**
 * A small PSR-11 container for plain PHP: what an application that runs on no
 * framework builds its services with, from the same list a Symfony container
 * or Laravel's reads (omnisense/omnibase's Base\Services::definitions()), so
 * that no service is wired three times.
 *
 * Every service is shared (built once, on its first get()). A service is:
 * - an object given (set()), or a factory (factory(): fn (Container): object);
 * - an alias of another (alias(): an interface answered by a class);
 * - a class, defined (define(), or a definition as data: its arguments by
 *   name or position, its bindings, factory, calls, tags and decoration) or
 *   not: a class that can be instantiated is autowired when asked, has()
 *   answers true.
 *
 * Autowiring reads each constructor parameter, in this order:
 * 1. an argument given by its name (`$name` or `name`) or its position (0, 1...):
 *    see value() for what it may be;
 * 2. a #[Autowire] attribute (Symfony's, read by its name, never loaded:
 *    its value, param, service or env argument), so a class written for
 *    Symfony needs nothing more - a service it names that the container
 *    does not have leaves the parameter to its type;
 * 3. a value bound to its name (a definition's `bind`);
 * 4. its type: first a class or interface of a union that something answers,
 *    then, for a parameter that is not optional, a class that can be built;
 * 5. its default value, else null when it allows null.
 * Anything else is refused aloud (ContainerException), naming the service and
 * the parameter; a cycle too.
 *
 * Parameters are plain values by name ('base.images.formats'...), given at
 * construction or by setParameter(); '%env(NAME)%' reads the environment
 * ('%env(bool:NAME)%', 'int:', 'default::' as Symfony reads them).
 */
final class Container implements ContainerInterface
{
    /** The attribute a class written for Symfony marks a parameter with. Read by name, never loaded. */
    public const AUTOWIRE_ATTRIBUTE = 'Symfony\Component\DependencyInjection\Attribute\Autowire';

    /** @var array<string, mixed> */
    private array $services = [];
    /** @var array<string, \Closure(self): mixed> */
    private array $factories = [];
    /** @var array<string, string> */
    private array $aliases = [];
    /** @var array<string, array{class: class-string, arguments: array<int|string, mixed>, bind?: array<string, mixed>, calls?: list<array<mixed>>, factory?: mixed, inner?: string|null}> */
    private array $definitions = [];
    /** @var array<string, list<string>> services by tag, in their order */
    private array $tags = [];
    /** @var array<string, true> */
    private array $resolving = [];

    /** @param array<string, mixed> $parameters */
    public function __construct(private array $parameters = [])
    {
        $this->services[ContainerInterface::class] = $this;
        $this->services[self::class] = $this;
    }

    /**
     * A container built from a list of definitions, as data (Omnibridge\Container\Declarations: each a
     * class, the contracts it answers, its arguments, bindings, factory, calls, tags and decoration). A
     * definition whose requirements are not installed or whose environment is not this one (the parameter
     * `kernel.environment`) is left out, as are its contracts; one whose class does not exist is not a
     * service (has() answers false, its class asked only then); what only Symfony reads (a definition's
     * `symfony` part) is not read here.
     *
     * A registry of what is discovered among them (Container\Discovery: `autoconfigure`, `collect`, `fallbacks`;
     * omnisense/omnibase's Base\Discovery::registries()) adds the tags autoconfiguration gives, the calls handing each
     * collection to its collecting service, and the services no one else binds.
     *
     * The persistence (omnisense/omnibase's plan "omnibase agnostic", step F7d) is found as every host finds it
     * (Persistence\Persistences::choose()): the parameter `persistence` names it (doctrine, eloquent), required when
     * several are installed, else the only one installed is it. Its own definitions come first (omnibridge/eloquent's:
     * its unit of work, backend, repositories and the repository interfaces), so that its contracts are answered by
     * it; the parameter `persistence` is then the name chosen. A persistence declaring none (omnibridge/doctrine) is
     * the application's to give: its unit of work as a service.
     *
     * @param iterable<array<string, mixed>> $definitions
     * @param array<string, mixed>           $parameters
     * @param array<string, object>          $services    objects given (the host's contracts, a logger...)
     * @param array<string, mixed>           $discovery   what is discovered among them (Container\Discovery's kinds)
     *
     * @throws \Omnibridge\Exception\PersistenceException the parameter `persistence` names none installed, or several are installed and it names none
     */
    public static function fromDefinitions(iterable $definitions, array $parameters = [], array $services = [], array $discovery = []): self
    {
        $configured = $parameters['persistence'] ?? null;
        $persistence = Persistences::choose(\is_string($configured) ? $configured : null, 'the container\'s parameter "persistence"');
        if (null !== $persistence) {
            $parameters['persistence'] = $persistence;
            $definitions = [...Persistences::definitions($persistence), ...(\is_array($definitions) ? array_values($definitions) : iterator_to_array($definitions, false))];
        }
        $container = new self($parameters);
        $environment = $parameters['kernel.environment'] ?? null;
        $resolved = Declarations::resolve($definitions, \is_string($environment) ? $environment : null);
        $resolved = Discovery::autoconfigure($resolved, $discovery['autoconfigure'] ?? []);
        foreach (Discovery::collected($resolved, $discovery['collect'] ?? []) as $id => $calls) {
            foreach ($calls as [$method, $tagged]) {
                // A service that is none here (its class not installed) is not handed: '@??' drops the call.
                $resolved[$id]['calls'][] = [$method, ['@??'.$tagged]];
            }
        }
        foreach ($resolved as $id => $definition) {
            $container->definitions[$id] = [
                'class' => $definition['class'],
                'arguments' => $definition['arguments'],
                'bind' => $definition['bind'],
                'calls' => $definition['calls'],
                'factory' => $definition['factory'],
            ];
            foreach (array_keys($definition['contracts']) as $contract) {
                if ($contract !== $id && !isset($container->aliases[$contract])) {
                    $container->aliases[$contract] = $id;
                }
            }
            foreach ($definition['tags'] as $tag) {
                $container->tags[\is_string($tag) ? $tag : $tag['name']][] = $id;
            }
        }
        foreach ($services as $id => $service) {
            $container->set($id, $service);
        }
        $defined = static fn (string $id): bool => isset($container->definitions[$id]) || isset($container->services[$id]) || isset($container->factories[$id]) || isset($container->aliases[$id]);
        foreach ($discovery['fallbacks'] ?? [] as $id => $fallback) {
            if (!$defined($id)) {
                $bound = Discovery::fallback($fallback, $defined);
                isset($bound['alias']) ? $container->aliases[$id] = $bound['alias'] : $container->definitions[$id] = ['class' => $bound['class'], 'arguments' => []];
            }
        }
        // Decorations last, once every service is known: the decorated id answers the decorator, which is given the
        // former service as '@.inner'; a decoration of a service that is not there is dropped (`on_invalid: ignore`)
        // or given null (`null`), else the decorator asks for it and fails aloud when built.
        foreach ($resolved as $id => $definition) {
            if (null === $decorates = $definition['decorates']) {
                continue;
            }
            $decorated = $decorates['id'];
            $target = $container->target($decorated);
            $there = isset($container->services[$target]) || isset($container->factories[$target]) || isset($container->definitions[$target]);
            if (!$there || $target === $id) {
                if ('ignore' === ($decorates['on_invalid'] ?? null)) {
                    unset($container->definitions[$id]);
                    continue;
                }
                $container->definitions[$id]['inner'] = 'null' === ($decorates['on_invalid'] ?? null) ? null : $decorated;
                continue;
            }
            $inner = $id.'.inner';
            foreach (['services', 'factories', 'definitions'] as $kind) {
                if (\array_key_exists($target, $container->$kind)) {
                    $container->$kind[$inner] = $container->$kind[$target];
                    unset($container->$kind[$target]);
                }
            }
            $container->aliases[$target] = $id;
            if ($target !== $decorated) {
                $container->aliases[$decorated] = $id;
            }
            $container->definitions[$id]['inner'] = $inner;
        }

        return $container;
    }

    public function set(string $id, object $service): self
    {
        unset($this->aliases[$id], $this->factories[$id], $this->definitions[$id]);
        $this->services[$id] = $service;

        return $this;
    }

    /** @param \Closure(self): mixed $factory called once, on the first get() */
    public function factory(string $id, \Closure $factory): self
    {
        unset($this->aliases[$id], $this->services[$id], $this->definitions[$id]);
        $this->factories[$id] = $factory;

        return $this;
    }

    public function alias(string $id, string $target): self
    {
        if ($id === $target) {
            throw new ContainerException(sprintf('"%s" cannot be an alias of itself.', $id));
        }
        unset($this->services[$id], $this->factories[$id], $this->definitions[$id]);
        $this->aliases[$id] = $target;

        return $this;
    }

    /**
     * @param class-string|null        $class     the class built (the id itself by default)
     * @param array<int|string, mixed> $arguments by parameter name ('$' or not) or position
     */
    public function define(string $id, ?string $class = null, array $arguments = []): self
    {
        $class ??= $id;
        if (!class_exists($class)) {
            throw new ContainerException(sprintf('The service "%s" names a class that does not exist: "%s".', $id, $class));
        }
        unset($this->aliases[$id], $this->services[$id], $this->factories[$id]);
        $this->definitions[$id] = ['class' => $class, 'arguments' => $arguments];

        return $this;
    }

    /**
     * The services a definition tags so, in their order (what `['!tagged_iterator' => $tag]` gives).
     *
     * @return list<mixed>
     */
    public function tagged(string $tag): array
    {
        return array_map($this->get(...), array_values(array_filter($this->tags[$tag] ?? [], $this->has(...))));
    }

    /**
     * The ids the container defines or was given (not the classes it would autowire), in their order.
     *
     * @return list<string>
     */
    public function ids(): array
    {
        return array_values(array_unique([...array_keys($this->services), ...array_keys($this->factories), ...array_keys($this->definitions)]));
    }

    public function setParameter(string $name, mixed $value): self
    {
        $this->parameters[$name] = $value;

        return $this;
    }

    public function hasParameter(string $name): bool
    {
        return \array_key_exists($name, $this->parameters);
    }

    public function getParameter(string $name): mixed
    {
        if (str_starts_with($name, 'env(') && str_ends_with($name, ')')) {
            $variable = substr($name, 4, -1);
            $processors = explode(':', $variable);
            $variable = array_pop($processors);
            $value = getenv($variable);
            // The default a host gives an unset variable (Symfony's 'env(NAME)' parameter).
            $value = false === $value ? ($this->parameters['env('.$variable.')'] ?? null) : $value;
            foreach (array_reverse($processors) as $processor) {
                $value = match ($processor) {
                    'bool' => null === $value ? false : filter_var($value, \FILTER_VALIDATE_BOOL),
                    'int' => (int) $value,
                    'float' => (float) $value,
                    'string' => (string) $value,
                    default => $value, // default:: (an empty default), resolve: as given
                };
            }

            return $value;
        }
        if (!\array_key_exists($name, $this->parameters)) {
            throw new ServiceNotFoundException(sprintf('No parameter "%s" in the container.', $name));
        }

        return $this->parameters[$name];
    }

    public function has(string $id): bool
    {
        $id = $this->target($id);
        if (isset($this->definitions[$id])) {
            // A class not installed (a suggested package's) is not a service, nor are its contracts.
            return null !== ($this->definitions[$id]['factory'] ?? null)
                || (Declarations::loadable($this->definitions[$id]['class']) && (new \ReflectionClass($this->definitions[$id]['class']))->isInstantiable());
        }

        return isset($this->services[$id]) || isset($this->factories[$id])
            || (Declarations::loadable($id) && (new \ReflectionClass($id))->isInstantiable());
    }

    public function get(string $id): mixed
    {
        $id = $this->target($id);
        if (\array_key_exists($id, $this->services)) {
            return $this->services[$id];
        }
        if (isset($this->resolving[$id])) {
            throw new ContainerException(sprintf('A cycle: "%s" needs itself (%s).', $id, implode(' -> ', [...array_keys($this->resolving), $id])));
        }

        $this->resolving[$id] = true;
        try {
            if (isset($this->factories[$id])) {
                $service = ($this->factories[$id])($this);
            } elseif (isset($this->definitions[$id]) && $this->has($id)) {
                $service = $this->build($id, $this->definitions[$id]);
            } elseif (!isset($this->definitions[$id]) && Declarations::loadable($id) && (new \ReflectionClass($id))->isInstantiable()) {
                $service = $this->build($id, ['class' => $id, 'arguments' => []]);
            } else {
                throw new ServiceNotFoundException(sprintf('No service "%s" in the container: set(), factory(), alias() or define() it.', $id));
            }
        } finally {
            unset($this->resolving[$id]);
        }

        return $this->services[$id] = $service;
    }

    /**
     * A value given: '@id' a service, '@?id' (and Symfony's '@??id', '@!id') that service or null where there
     * is none, '@.inner' the service a decoration wraps, '%name%' a parameter (inside a string, replaced), '@@'
     * and '%%' escaped; `['!tagged_iterator' => tag]` the services of a tag, `['!service_closure' => '@id']` a
     * closure returning it, `['!closure' => callable]` the callable as a Closure, `['!new' => ['class' => ...,
     * 'arguments' => [...]]]` an object built there, `['!iterator' => [...]]` the values; arrays item by item.
     *
     * @param list<string|null> $inner the service a decoration wraps, when there is one
     */
    public function value(mixed $value, array $inner = []): mixed
    {
        if (\is_array($value)) {
            if (1 === \count($value) && \is_string($key = array_key_first($value)) && str_starts_with($key, '!')) {
                $given = $value[$key];

                return match ($key) {
                    '!tagged_iterator' => $this->tagged($given),
                    '!service_closure' => fn (): mixed => $this->value($given, $inner),
                    '!closure' => \Closure::fromCallable($this->value($given, $inner)),
                    '!new' => $this->build($given['class'], ['class' => $given['class'], 'arguments' => $given['arguments'] ?? []]),
                    '!iterator' => $this->value($given, $inner),
                    default => throw new ContainerException(sprintf('Unknown value "%s".', $key)),
                };
            }

            return array_map(fn (mixed $item): mixed => $this->value($item, $inner), $value);
        }
        if (!\is_string($value)) {
            return $value;
        }
        if (str_starts_with($value, '@@')) {
            return substr($value, 1);
        }
        if (str_starts_with($value, '@')) {
            $optional = str_starts_with($value, '@?') || str_starts_with($value, '@!');
            $id = ltrim(substr($value, 1), '?!');
            if ('.inner' === $id) {
                if (null === $id = $inner[0] ?? null) {
                    return null;
                }
            }
            if ($optional && !$this->has($id)) {
                return null;
            }

            return $this->get($id);
        }
        if (preg_match('/^%([^%\s]+)%$/', $value, $match)) {
            return $this->getParameter($match[1]);
        }

        return preg_replace_callback('/%%|%([^%\s]+)%/', function (array $match): string {
            if ('%%' === $match[0]) {
                return '%';
            }
            $parameter = $this->getParameter($match[1]);
            if (null !== $parameter && !\is_scalar($parameter)) {
                throw new ContainerException(sprintf('The parameter "%s" is not a scalar: it cannot be written inside a string.', $match[1]));
            }

            return (string) $parameter;
        }, $value);
    }

    /** The id an alias leads to, followed to the end. */
    private function target(string $id): string
    {
        $seen = [];
        while (isset($this->aliases[$id])) {
            if (isset($seen[$id])) {
                throw new ContainerException(sprintf('A cycle of aliases at "%s".', $id));
            }
            $seen[$id] = true;
            $id = $this->aliases[$id];
        }

        return $id;
    }

    /** @param array{class: class-string, arguments: array<int|string, mixed>, bind?: array<string, mixed>, calls?: list<array<mixed>>, factory?: mixed, inner?: string|null} $definition */
    private function build(string $id, array $definition): object
    {
        $class = $definition['class'];
        $inner = \array_key_exists('inner', $definition) ? [$definition['inner']] : [];
        $resolve = fn (mixed $value): mixed => $this->value($value, $inner);
        $arguments = $definition['arguments'];
        $bind = $definition['bind'] ?? [];

        if (null !== $factory = $definition['factory'] ?? null) {
            $callable = $resolve($factory);
            $function = \is_array($callable) ? new \ReflectionMethod($callable[0], $callable[1]) : new \ReflectionFunction(\Closure::fromCallable($callable));
            $service = $callable(...$this->parameters($id, $class, $function->getParameters(), $arguments, $bind, $resolve));
        } else {
            $reflection = new \ReflectionClass($class);
            if (!$reflection->isInstantiable()) {
                throw new ContainerException(sprintf('The service "%s" is a %s, which cannot be instantiated.', $id, $class));
            }
            $constructor = $reflection->getConstructor();
            $service = null === $constructor ? $reflection->newInstance() : $reflection->newInstanceArgs($this->parameters($id, $class, $constructor->getParameters(), $arguments, $bind, $resolve));
        }

        foreach ($definition['calls'] ?? [] as $call) {
            $values = [];
            foreach ($call[1] ?? [] as $key => $value) {
                // A service that may be ignored ('@??id', '@!id') and is not there drops the call, as under Symfony.
                if (\is_string($value) && (str_starts_with($value, '@??') || str_starts_with($value, '@!')) && !$this->has(ltrim(substr($value, 1), '?!'))) {
                    continue 2;
                }
                $values[$key] = $resolve($value);
            }
            $result = $service->{$call[0]}(...$values);
            if (!empty($call[2]) && \is_object($result)) {
                $service = $result;
            }
        }

        return $service;
    }

    /**
     * The values of a constructor's (or a factory's) parameters.
     *
     * @param list<\ReflectionParameter> $parameters
     * @param array<int|string, mixed>   $arguments
     * @param array<string, mixed>       $bind
     *
     * @return array<string, mixed>
     */
    private function parameters(string $id, string $class, array $parameters, array $arguments, array $bind, \Closure $resolve): array
    {
        $values = [];
        foreach ($parameters as $position => $parameter) {
            if ($parameter->isVariadic()) {
                break;
            }
            $name = $parameter->getName();
            foreach (['$'.$name, $name, $position] as $key) {
                if (\array_key_exists($key, $arguments)) {
                    $values[$name] = $resolve($arguments[$key]);
                    continue 2;
                }
            }
            [$found, $value] = $this->autowired($parameter);
            if ($found) {
                $values[$name] = $value;
                continue;
            }
            if (\array_key_exists('$'.$name, $bind)) {
                $values[$name] = $resolve($bind['$'.$name]);
                continue;
            }
            [$found, $value] = $this->typed($parameter);
            if ($found) {
                $values[$name] = $value;
                continue;
            }
            if ($parameter->isDefaultValueAvailable()) {
                continue;
            }
            if ($parameter->allowsNull()) {
                $values[$name] = null;
                continue;
            }

            throw new ContainerException(sprintf('Cannot autowire the service "%s": argument $%s of %s::__construct() (%s) has no service, no parameter given, and no default.', $id, $name, $class, (string) $parameter->getType()));
        }

        return $values;
    }

    /** @return array{bool, mixed} whether Symfony's #[Autowire] gives the parameter a value, and that value */
    private function autowired(\ReflectionParameter $parameter): array
    {
        foreach ($parameter->getAttributes() as $attribute) {
            if (self::AUTOWIRE_ATTRIBUTE !== $attribute->getName()) {
                continue;
            }
            $arguments = $attribute->getArguments();
            // A service Symfony's attribute names that this container does not have (`advanced_router`...): the
            // parameter is answered by its type instead, as a host without Symfony's ids gives it.
            if (isset($arguments['service'])) {
                if (!$this->has($arguments['service'])) {
                    return [false, null];
                }

                return [true, $this->get($arguments['service'])];
            }
            if (isset($arguments['param'])) {
                return [true, $this->getParameter($arguments['param'])];
            }
            if (isset($arguments['env'])) {
                return [true, $this->getParameter('env('.$arguments['env'].')')];
            }
            if (\array_key_exists('value', $arguments) || \array_key_exists(0, $arguments)) {
                return [true, $this->value($arguments['value'] ?? $arguments[0])];
            }
        }

        return [false, null];
    }

    /** @return array{bool, mixed} whether the parameter's type has a service, and that service */
    private function typed(\ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();
        $types = $type instanceof \ReflectionUnionType ? $type->getTypes() : (null === $type ? [] : [$type]);
        $names = [];
        foreach ($types as $named) {
            if ($named instanceof \ReflectionNamedType && !$named->isBuiltin() && !\in_array($named->getName(), ['self', 'static'], true)) {
                $names[] = $named->getName();
            }
        }
        // First a type something answers, whichever its place in the union (a contract before the framework's
        // class it replaces: nothing of a type not asked for is autoloaded); then a class that can be built,
        // unless the parameter has a default or allows null (an optional service left out, as Symfony does).
        foreach ($names as $name) {
            $target = $this->target($name);
            if (isset($this->services[$target]) || isset($this->factories[$target]) || (isset($this->definitions[$target]) && $this->has($target))) {
                return [true, $this->get($name)];
            }
        }
        if (!$parameter->isDefaultValueAvailable() && !$parameter->allowsNull()) {
            foreach ($names as $name) {
                if ($this->has($name)) {
                    return [true, $this->get($name)];
                }
            }
        }

        return [false, null];
    }
}
