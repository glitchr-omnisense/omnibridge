---
title: Installation
order: 10
---

# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.2 or later | readonly properties, enumerations, first-class callables |
| `psr/container` | ^1.1 or ^2.0 | `Runtime::setContainer()` takes a PSR-11 container |
| `psr/clock` | ^1.0 | `Runtime::clock()`, `SystemClock` |
| `psr/event-dispatcher` | ^1.0 | `Runtime::events()` |

Nothing else is required: no framework, no ORM, no extension that composer.json declares. Two
optional needs:

- `Schema\Database\MySqlSchema::read()` takes a `\PDO` connected to MySQL (`ext-pdo`, `ext-pdo_mysql`
  in the application).
- `Schema\CamelCaseNaming` calls `mb_strtolower()`, `mb_substr()` and `mb_strtoupper()`.

> [!WARNING]
> Known defect: `CamelCaseNaming` calls the `mb_*` functions while composer.json requires neither
> `ext-mbstring` nor a polyfill, where a requirement of the package should be declared. Every PHP
> image of the family has mbstring; a PHP built without it fails on the first table name.

```sh
composer require omnisense/omnibridge:1.x-dev
```

The branch `1.x` is published as `1.x-dev` (`extra.branch-alias`). The package `replace`s
`omniscient/omnibridge`, its former name.

## Plain PHP

The registry needs no host: set the services by hand. A complete script, with an `Urls` of its own:

```php
<?php

require 'vendor/autoload.php';

use Omnibridge\Contract\Urls;
use Omnibridge\Exception\MissingContractException;
use Omnibridge\Runtime;

final class PlainUrls implements Urls
{
    public function path(string $name, array $parameters = []): string
    {
        return '/'.$name.($parameters ? '/'.implode('/', $parameters) : '');
    }

    public function url(string $name, array $parameters = []): string
    {
        return 'https://example.org'.$this->path($name, $parameters);
    }

    public function asset(string $path): string
    {
        return '/build/'.ltrim($path, '/');
    }
}

Runtime::alias(Urls::class, 'Legacy\Contract\Urls');
Runtime::set(Urls::class, new PlainUrls());

echo Runtime::urls()->path('article', ['slug' => 'le-fuji']), "\n";
echo Runtime::get('Legacy\Contract\Urls')->url('article', ['slug' => 'le-fuji']), "\n";
echo get_class(Runtime::clock()), "\n";
var_dump(Runtime::find(\Omnibridge\Contract\Access::class));

try {
    Runtime::translations();
} catch (MissingContractException $e) {
    echo $e->getMessage(), "\n";
}
```

Its output, run in the package's harness image (PHP 8.2.34, 2026-10-27):

```text
/article/le-fuji
https://example.org/article/le-fuji
Omnibridge\SystemClock
NULL
No Omnibridge\Contract\Translations in the runtime: the host fills it at boot (its omnibridge bridge, omnisense/omnibase's bundle under Symfony), or Omnibridge\Runtime::set() / setContainer() before it is asked.
```

- The former name `Legacy\Contract\Urls` is the same interface (`class_alias`): asked by it, the
  registry answers the service set under the new name.
- No clock was given: the machine's (`SystemClock`).
- `find()` answers null for a contract nobody filled; `translations()` (like every accessor but
  `clock()`) throws.

A container works the same way: any PSR-11 container whose ids are the interfaces' names.

```php
Runtime::setContainer($container);   // $container->has(Urls::class), ->get(Urls::class)...
```

## Under a framework

The application does not fill the registry itself: its bridge does, at boot.

| Host | Package | What fills the registry |
|---|---|---|
| Symfony | `omnibridge/symfony` | `OmnibridgeBundle::boot()` hands `Runtime` the service locator `omnibridge.contracts`; on an omnisense/omnibase site, `BaseBundle` does it with omnibase's own contracts added ([omnibridge/symfony](https://github.com/glitchr-omnisense/omnibridge-symfony)) |
| Laravel | `omnibridge/laravel` | `OmnibridgeServiceProvider` binds each contract under its interface's name and hands `Runtime` the application itself ([omnibridge/laravel](https://github.com/glitchr-omnisense/omnibridge-laravel)) |
| Twig, anywhere | `omnibridge/twig` | nothing: its extensions ask the contracts they are given, or `Runtime` |

Each bridge's manual has its own installation page. A host bridge requires this package; this
package requires none of them.
