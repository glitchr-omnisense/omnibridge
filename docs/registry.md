---
title: The registry
order: 30
---

# The registry

`Omnibridge\Runtime` is the registry through which an application's framework-free code reaches the
contracts its host answers: an entity's getter, a value object, a screen's builder asks
`Runtime::urls()`, `::access()`... instead of a framework's services. Every method is static; the
state is the class's own (private static properties), shared by any class that extends it.

## Filling the registry

A host fills it once, at boot, in one of two ways - or both.

### With a container

```php
Runtime::setContainer(?Psr\Container\ContainerInterface $container): void
Runtime::getContainer(): ?Psr\Container\ContainerInterface
```

A PSR-11 container whose ids are the contracts' interface names. Under Symfony it is a service
locator (`omnibridge.contracts`, made by omnibridge/symfony's `ContractsPass`; `base.contracts` on an
omnisense/omnibase site); under Laravel the application itself, whose bindings are named by the
interfaces (omnibridge/laravel's `OmnibridgeServiceProvider`).

- Each contract is resolved from the container the first time it is asked, then kept.
- A different container (another kernel booted, a test's new application) forgets every service
  resolved from the previous one. Setting the same container again keeps them.
- `setContainer(null)` detaches the container: omnibridge/symfony's bundle does it at shutdown,
  when the registry still holds its locator.

### One service at a time

```php
Runtime::set(string $contract, ?object $service): void
```

A service given by hand wins over the container's. It must implement the contract, or
`MissingContractException` is thrown at once ("The runtime's X is a Y, which does not implement
it."). `set($contract, null)` takes it back, and forgets what the container gave for it too, so the
next call resolves again.

## Asking the registry

| Method | Returns | When nothing answers |
|---|---|---|
| `Runtime::get(string $contract)` | the service (`T` for `class-string<T>`) | throws `MissingContractException`, naming the contract and its former names |
| `Runtime::find(string $contract)` | the service or `null` | `null`, for a caller that can do without |
| `Runtime::has(string $contract)` | `bool` | `false`; true when set by hand, already resolved, or the container has an id for it |

The order of the lookup: the service set by hand, then the one already resolved, then the
container, asked by the contract's own name, then by the name asked, then by each former name
(the first id the container `has()` is used). A service the container gives that does not implement
the contract throws `MissingContractException::notAn()`.

Any interface name can be asked: `get()` is not limited to the nine contracts. A family keeps its
own contracts in the same registry (omnibase's `Base\Contract\Nouns`...).

### The accessors

Typed shortcuts for `get()`:

| Accessor | Contract |
|---|---|
| `Runtime::urls()` | `Omnibridge\Contract\Urls` |
| `Runtime::translations()` | `Omnibridge\Contract\Translations` |
| `Runtime::locales()` | `Omnibridge\Contract\Locales` |
| `Runtime::access()` | `Omnibridge\Contract\Access` |
| `Runtime::sessions()` | `Omnibridge\Contract\Sessions` |
| `Runtime::tokens()` | `Omnibridge\Contract\Tokens` |
| `Runtime::files()` | `Omnibridge\Contract\Files` |
| `Runtime::routes()` | `Omnibridge\Contract\Routes` |
| `Runtime::environment()` | `Omnibridge\Contract\Environment` |
| `Runtime::clock()` | `Psr\Clock\ClockInterface`; `Omnibridge\SystemClock` when nobody gave one (never throws) |
| `Runtime::events()` | `Psr\EventDispatcher\EventDispatcherInterface` |

The `SystemClock` fallback is kept as resolved: a container set afterwards (a different one) forgets
it, and the host's clock is asked again.

### Resetting

```php
Runtime::reset(): void
```

Everything forgotten: no container, nothing set, nothing resolved. The former names stay: they are
declarations made when the autoloader loaded, not state. A test's `tearDown()` calls it
([Testing](testing.md)).

## Former names

```php
Runtime::alias(string $contract, string $former): void
Runtime::formerNames(string $contract): list<string>
```

`alias()` keeps a contract (or any class of a family) reachable under a former name:

1. `class_alias($contract, $former)` - an alternate name, not a subclass: one interface, one
   reflection name. Skipped when a class or interface of that name is already loaded.
2. The registry told: a service bound in the container under the former name only is found when
   the new name is asked, and `MissingContractException` lists the former names.

The package that owned the name declares it, when Composer's autoloader loads (its `autoload.files`),
never on demand: PHP autoloads nothing for a parameter's type or an `instanceof`, so a lazy alias
would leave a type hint of the former name unknown. Declaring the same pair twice is harmless.

A name is always resolved to its own: `Runtime::get('Base\Contract\Urls')` and
`Runtime::get(Urls::class)` answer the same object, set under either name.

## A family's own registry

`Runtime` is not final. An application family adds its own contracts' accessors in a class of its
own over the same state:

```php
namespace Acme\Contract;

use Omnibridge\Runtime as Registry;

final class Runtime extends Registry
{
    public static function invoices(): Invoices
    {
        return self::get(Invoices::class);
    }
}
```

`Acme\Contract\Runtime::urls()` and `Omnibridge\Runtime::urls()` answer the same service; a service
set through either class is seen by both (the core's tests check it with a fixture
`FamilyRuntime`). omnisense/omnibase's `Base\Contract\Runtime` is such a class: its `parameters()`,
`nouns()`, `media()`, `pictures()`, `texts()` and `mailer()`.

## Where to ask it, and where not

- Ask it in code that cannot be given its dependencies: an entity, a value object, a static helper,
  a Twig page's data built outside a controller.
- In a service a framework builds, prefer a constructor argument typed by the contract: both
  bridges bind each adapter under its interface's name, and the test needs no registry.
- Ask at the moment of use, not in a constructor or a static initialiser: a long-running worker
  (FrankenPHP, Octane) may replace the container between two requests.
