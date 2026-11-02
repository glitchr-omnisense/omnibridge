# The container

`Omnibridge\Container` is a small PSR-11 container for an application that runs on no framework.
Symfony's container and Laravel's are what they are; this one exists so that a plain PHP script gets
the same services from the same list (plan "omnibase agnostic", step F5a, Marco's answer 3: the
services stay available everywhere).

```php
use Omnibridge\Container;

$container = Container::fromDefinitions(\Base\Services::definitions(), $parameters, [
    \Omnibridge\Contract\Urls::class => $urls,          // the host's contracts, given
    \Omnibridge\Contract\Views::class => $views,
]);
$media = $container->get(\Base\Service\MediaService::class);
```

## Services

Every service is shared: built once, on its first `get()`.

| Method | Meaning |
|---|---|
| `set(string $id, object $service)` | an object given |
| `factory(string $id, \Closure $factory)` | `fn (Container $c): mixed`, called once |
| `alias(string $id, string $target)` | an interface (or a former id) answered by another service |
| `define(string $id, ?string $class = null, array $arguments = [])` | a class and its arguments by name |
| `has(string $id)` / `get(string $id)` | PSR-11; an instantiable class not defined is autowired |
| `setParameter()`, `hasParameter()`, `getParameter()` | plain values by name; `env(NAME)` reads the environment |

## Autowiring

Each constructor parameter, in this order:

1. an argument given by its name (`$name` or `name`): `'@id'` a service, `'%name%'` a parameter,
   `'mail@%app.domain%'` a parameter written inside a string, `'@@'` and `'%%'` escaped; arrays item by
   item; any other value as it is;
2. Symfony's `#[Autowire]` (its `value`, `param`, `service` or `env`), read by the attribute's name: the
   attribute's class is never loaded, so a class written for Symfony needs nothing more;
3. its type, each class or interface of a union in turn, when the container has it (an interface only
   when something answers it; a class when it can be built, unless the parameter is optional);
4. its default value, else `null` when the type allows it.

Anything else throws `ContainerException`, naming the service, the class and the parameter; a cycle
too (`A cycle: "a" needs itself (a -> b -> a)`). A service nobody can build throws
`ServiceNotFoundException`, PSR-11's `NotFoundExceptionInterface`.

## Definitions as data

`fromDefinitions(iterable $definitions, array $parameters = [], array $services = [])` reads a list
whose items are:

```php
['class' => Base\Service\Obfuscator::class,                       // the class built
 'id' => 'base.service.obfuscator',                                // optional, the class by default
 'contracts' => [Base\Service\ObfuscatorInterface::class],         // aliases to it
 'arguments' => ['$cacheDir' => '%base.obfuscator.cache_dir%']]    // by name, as above
```

A definition whose class does not exist (a suggested package not installed) is left out with its
contracts. omnibridge/laravel binds the same list in Laravel's container, and omnisense/omnibase's
Symfony configuration aliases its contracts from it.
