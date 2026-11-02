<?php

namespace Omnibridge;

use Omnibridge\Exception\ContainerException;
use Omnibridge\Exception\ServiceNotFoundException;
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
 * - a class, defined (define(): its arguments by name) or not: a class
 *   that can be instantiated is autowired when asked, has() answers true.
 *
 * Autowiring reads each constructor parameter, in this order:
 * 1. an argument given by its name (`$name` or `name`): '@id' is a service,
 *    '%name%' a parameter, a string with '%name%' inside has it replaced,
 *    '@@' and '%%' escape them, any other value is given as it is;
 * 2. a #[Autowire] attribute (Symfony's, read by its name, never loaded:
 *    its value, param, service or env argument), so a class written for
 *    Symfony needs nothing more;
 * 3. its type, each class or interface of a union in turn, when the
 *    container has it;
 * 4. its default value, else null when it allows null.
 * Anything else is refused aloud (ContainerException), naming the service and
 * the parameter; a cycle too.
 *
 * Parameters are plain values by name ('base.images.formats'...), given at
 * construction or by setParameter(); '%env(NAME)%' reads the environment.
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
    /** @var array<string, array{class: class-string, arguments: array<string, mixed>}> */
    private array $definitions = [];
    /** @var array<string, true> */
    private array $resolving = [];

    /** @param array<string, mixed> $parameters */
    public function __construct(private array $parameters = [])
    {
        $this->services[ContainerInterface::class] = $this;
        $this->services[self::class] = $this;
    }

    /**
     * A container built from a list of definitions, as data: each a class,
     * the contracts (interfaces, former ids) it answers, its arguments by
     * name. A definition whose class does not exist (a suggested package not
     * installed) is left out, as are its contracts.
     *
     * @param iterable<array{class: class-string, id?: string, contracts?: list<string>, arguments?: array<string, mixed>}> $definitions
     * @param array<string, mixed>                                                                                         $parameters
     * @param array<string, object>                                                                                        $services   objects given (the host's contracts, a logger...)
     */
    public static function fromDefinitions(iterable $definitions, array $parameters = [], array $services = []): self
    {
        $container = new self($parameters);
        foreach ($definitions as $definition) {
            if (!class_exists($definition['class'])) {
                continue;
            }
            $id = $definition['id'] ?? $definition['class'];
            $container->define($id, $definition['class'], $definition['arguments'] ?? []);
            foreach ($definition['contracts'] ?? [] as $contract) {
                if ($contract !== $id && !isset($container->aliases[$contract])) {
                    $container->alias($contract, $id);
                }
            }
        }
        foreach ($services as $id => $service) {
            $container->set($id, $service);
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
     * @param class-string|null    $class     the class built (the id itself by default)
     * @param array<string, mixed> $arguments by parameter name, '$' or not
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
            $value = getenv(substr($name, 4, -1));

            return false === $value ? null : $value;
        }
        if (!\array_key_exists($name, $this->parameters)) {
            throw new ServiceNotFoundException(sprintf('No parameter "%s" in the container.', $name));
        }

        return $this->parameters[$name];
    }

    public function has(string $id): bool
    {
        $id = $this->target($id);

        return isset($this->services[$id]) || isset($this->factories[$id]) || isset($this->definitions[$id])
            || (class_exists($id) && (new \ReflectionClass($id))->isInstantiable());
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
            } elseif (isset($this->definitions[$id])) {
                $service = $this->build($id, $this->definitions[$id]['class'], $this->definitions[$id]['arguments']);
            } elseif (class_exists($id) && (new \ReflectionClass($id))->isInstantiable()) {
                $service = $this->build($id, $id, []);
            } else {
                throw new ServiceNotFoundException(sprintf('No service "%s" in the container: set(), factory(), alias() or define() it.', $id));
            }
        } finally {
            unset($this->resolving[$id]);
        }

        return $this->services[$id] = $service;
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

    /**
     * @param class-string         $class
     * @param array<string, mixed> $arguments
     */
    private function build(string $id, string $class, array $arguments): object
    {
        $reflection = new \ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new ContainerException(sprintf('The service "%s" is a %s, which cannot be instantiated.', $id, $class));
        }
        $constructor = $reflection->getConstructor();
        if (null === $constructor) {
            return $reflection->newInstance();
        }

        $values = [];
        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isVariadic()) {
                break;
            }
            $name = $parameter->getName();
            if (\array_key_exists('$'.$name, $arguments) || \array_key_exists($name, $arguments)) {
                $values[$name] = $this->resolve(\array_key_exists('$'.$name, $arguments) ? $arguments['$'.$name] : $arguments[$name]);
                continue;
            }
            [$found, $value] = $this->autowired($parameter);
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

        return $reflection->newInstanceArgs($values);
    }

    /** @return array{bool, mixed} whether a value was found for the parameter, and that value */
    private function autowired(\ReflectionParameter $parameter): array
    {
        foreach ($parameter->getAttributes() as $attribute) {
            if (self::AUTOWIRE_ATTRIBUTE !== $attribute->getName()) {
                continue;
            }
            $arguments = $attribute->getArguments();
            if (isset($arguments['service'])) {
                return [true, $this->get($arguments['service'])];
            }
            if (isset($arguments['param'])) {
                return [true, $this->getParameter($arguments['param'])];
            }
            if (isset($arguments['env'])) {
                return [true, $this->getParameter('env('.$arguments['env'].')')];
            }
            if (\array_key_exists('value', $arguments) || \array_key_exists(0, $arguments)) {
                return [true, $this->resolve($arguments['value'] ?? $arguments[0])];
            }
        }

        $type = $parameter->getType();
        $types = $type instanceof \ReflectionUnionType ? $type->getTypes() : (null === $type ? [] : [$type]);
        foreach ($types as $named) {
            if (!$named instanceof \ReflectionNamedType || $named->isBuiltin()) {
                continue;
            }
            $name = $named->getName();
            if ('self' === $name || 'static' === $name) {
                continue;
            }
            // An interface or abstract class only when something answers it; a class when it can be built,
            // unless the parameter has a default or allows null (an optional service left out, as Symfony does).
            $target = $this->target($name);
            $known = isset($this->services[$target]) || isset($this->factories[$target]) || isset($this->definitions[$target]);
            if ($known || (!$parameter->isDefaultValueAvailable() && !$parameter->allowsNull() && $this->has($name))) {
                return [true, $this->get($name)];
            }
        }

        return [false, null];
    }

    /** A value given: '@id' a service, '%name%' a parameter (inside a string, replaced), '@@' and '%%' escaped; arrays resolved item by item. */
    private function resolve(mixed $value): mixed
    {
        if (\is_array($value)) {
            return array_map($this->resolve(...), $value);
        }
        if (!\is_string($value)) {
            return $value;
        }
        if (str_starts_with($value, '@@')) {
            return substr($value, 1);
        }
        if (str_starts_with($value, '@')) {
            return $this->get(substr($value, 1));
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
}
