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

## The declaration's format

`Omnibridge\Container\Declarations` reads a list of definitions the way every host does (omnisense/omnibase's
`Base\Services` is one, 192 definitions; omnibridge/symfony compiles the same data into Symfony's container itself):

| Key | What it is |
|---|---|
| `class`, `id` | the class built (a child's parent's when left out: its id's when the id is a class), the service's id (the class by default) |
| `parent`, `abstract` | another definition whose class, arguments, bindings, calls and factory it inherits (the child's own over them); an abstract one is a parent only |
| `requires`, `environment` | classes or interfaces that must exist; the one environment it is registered in (`kernel.environment`) |
| `arguments` | by position (`0`, `1`, `index_N` over a parent's) or by name (`$name`) |
| `bind` | values by parameter name, for what the arguments leave out |
| `factory`, `calls` | `[class, method]` or `['@id', method]`; `[method, [arguments]]` (a call whose argument is `'@??id'` or `'@!id'` and not there is dropped) |
| `decorates` | `id`, `on_invalid` (`ignore`: dropped when the decorated service is not there); the decorator is given it as `'@.inner'` |
| `tags` | a name, or `['name' => ..., attributes...]`; `['!tagged_iterator' => name]` gives the services of a tag, in order |
| `contracts` | aliases: a list item, or `alias => ['public' => ..., 'target' => ...]` |
| `autoconfigure` | the definition (or its parent) takes the tags a registry's `autoconfigure` gives its class (below) |
| `symfony`, `public`, `shared`, `lazy`, `autowire` | read by omnibridge/symfony only |

Values: `'@id'`, `'@?id'` (null when absent), `'%name%'` (and inside a string), `'%env(bool:NAME)%'`, `'@@'`/`'%%'`
escapes, `['!service_closure' => '@id']`, `['!closure' => callable]`, `['!new' => ['class' => ..., 'arguments' => [...]]]`,
`['!iterator' => [...]]`. No class is loaded while the list is read: a class that does not exist (or is abstract) is not a
service, which `has()` answers when asked. A service Symfony's `#[Autowire]` names and the container lacks
(`advanced_router`) leaves the parameter to its type.

## What is discovered

`fromDefinitions()`'s fourth argument is a registry of what a host discovers among the definitions, as data too
(`Omnibridge\Container\Discovery`; omnisense/omnibase's `Base\Discovery::registries()` is one: omnibridge/symfony
compiles it in one compiler pass, omnibridge/laravel applies it to what it binds):

| Kind | What it is |
|---|---|
| `autoconfigure` | `['instanceof' => class, 'tags' => [...], 'requires' => [...]]`: an autoconfigured definition whose class is one gets these tags after its own (Symfony's `registerForAutoconfiguration()`) |
| `collect` | `['service' => id, 'method' => name, 'tag' => tag]` (or `'instanceof' => class`): each tagged service, in its order, handed to the collecting service (`$service->add($tagged)`); one that is not a service here is dropped |
| `fallbacks` | `id => ['candidates' => [...], 'default' => class]`: a service no one binds is the first candidate defined, else the default class |
| `preferred` | `alias => ['prefer' => id, 'over' => id]`: read by the hosts whose aliases name pools (omnibridge/symfony) |

Any other kind (omnibase's contracts, enumerations, repositories, storages...) is a host's to compile.
`Discovery::attributes($resolved, $tag)` gives each service's tags of one name with their attributes (an entity
listener's `event`), for a host that registers them itself.
