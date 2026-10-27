# omnisense/omnibridge

The core of the omnibridge family: the contracts any host answers for an application that runs on
more than one framework, and the registry the application reaches them through. No framework, no
ORM, no template engine: the package requires PHP and three PSR interfaces (container, clock, event
dispatcher), and its `BareTest` proves that every class loads with nothing else.

```sh
composer require omnisense/omnibridge:1.x-dev
```

The bridges, `omnibridge/<host>`, answer these contracts with their host's services and fill the
registry at boot: [omnibridge/symfony](https://github.com/glitchr-omnisense/omnibridge-symfony)
(Symfony), [omnibridge/laravel](https://github.com/glitchr-omnisense/omnibridge-laravel) (Laravel),
[omnibridge/twig](https://github.com/glitchr-omnisense/omnibridge-twig) (the contract a Twig page
asks of its host), [omnibridge/doctrine](https://github.com/glitchr-omnisense/omnibridge-doctrine)
(Doctrine ORM over the neutral schema), [omnibridge/react](https://github.com/glitchr-omnisense/omnibridge-react)
(a React page over a screen's data), [omnibridge/static](https://github.com/glitchr-omnisense/omnibridge-static)
(a site exported to files) and [omnibridge/composer](https://github.com/glitchr-omnisense/omnibridge-composer)
(patches and stubs at install). A bridge is only the adapter of the contracts for one technique: it
chooses no versions, and an application that speaks only to the contracts changes host by changing
bridge and configuration.

```php
use Omnibridge\Runtime;

Runtime::urls()->path('article', ['slug' => 'le-fuji']);   // the host's router
Runtime::translations()->trans('menu.home');                // the host's catalogues
Runtime::access()->isGranted('ROLE_ADMIN');                 // the host's security
Runtime::clock()->now();                                    // the host's clock, else the machine's
```

Published as `omnisense/omnibridge`, formerly `omniscient/omnibridge`: the package `replace`s the old
name, so a `composer.json` that still requires `omniscient/omnibridge` resolves to this package and
never installs both. The namespace is `Omnibridge\`.

## When to use it

- Code that must not know its framework: an entity's getter that makes a link, a value object that
  needs the current locale, a screen's builder that asks whether the visitor is an administrator.
  It asks `Runtime::urls()`, `Runtime::locales()`, `Runtime::access()` instead of a router, a
  request stack or an authorization checker.
- An application rendered by two hosts from one code base and one database (lesringostars: Symfony
  and Laravel), where each host fills the same registry with its own services.
- A persistence layer that must find the same tables as Doctrine without Doctrine: the
  [schema](schema.md) describes the entities' tables from their attributes alone.

It is not a framework, a service container or a dependency-injection library: it holds one service
per contract, given by the host. Code that runs inside a framework and can be autowired should keep
being autowired - the bridges bind every contract under its interface's name in the host's
container too.

## The contracts

`Omnibridge\Contract\*`, each generic to any host ([Contracts](contracts.md) has every method):

| Contract | What it answers | Why it is generic |
|---|---|---|
| `Urls` | a route's path and whole URL by its name, a public file's address | every host has named routes and serves public files |
| `Translations` | a message from the catalogues (ICU), whether a key exists | every host has message catalogues and can tell whether one holds a key |
| `Locales` | the current, default and available locales (`fr-FR`) | every host has a current and a default locale |
| `Access` | whether the visitor holds an attribute, on a subject; the user | Symfony's authorization checker, Laravel's gate: the same two questions |
| `Sessions` | a user signed out when it is the one signed in | every host with sign-in has a sign-out |
| `Tokens` | a form's token for an intent (CSRF), and whether a token sent back is valid | every host protects its forms; the token is checked by the host that made it |
| `Files` | a stored file's public path and URL, its local path, whether its storage is remote | storages named by the application's configuration, mapped by each bridge to its host's (Flysystem, disks) |
| `Routes` | a route's declared path, the route of an address, its icons | every router knows a route's pattern and matches an address; icons are metadata, null where the host keeps none |
| `Environment` | the environment's name, debug, the secret | every host runs in an environment with a secret |

Also here: PSR-20's clock (`Runtime::clock()`, `SystemClock` when the host gave none) and PSR-14's
event dispatcher (`Runtime::events()`), which need no contract of their own.

Which bridge answers which contract:

| Contract | omnibridge/symfony | omnibridge/laravel |
|---|---|---|
| `Urls` | `Omnibridge\Symfony\RouterUrls` | `Omnibridge\Laravel\RouterUrls` |
| `Translations` | `Omnibridge\Symfony\TranslatorTranslations` | `Omnibridge\Laravel\CatalogueTranslations` |
| `Environment` | `Omnibridge\Symfony\Contract\KernelEnvironment` | `Omnibridge\Laravel\Contract\AppEnvironment` |
| `Access` | `Omnibridge\Symfony\Contract\SecurityAccess` | `Omnibridge\Laravel\Contract\GateAccess` |
| `Sessions` | `Omnibridge\Symfony\Contract\SecuritySessions` | `Omnibridge\Laravel\Contract\GuardSessions` |
| `Tokens` | `Omnibridge\Symfony\Contract\CsrfTokens` | `Omnibridge\Laravel\Contract\SessionTokens` |
| `Locales`, `Files`, `Routes` | not bound: omnisense/omnibase binds them (`Base\Bridge\Symfony\*`) | `AppLocales`, `DiskFiles`, `RouterRoutes` |
| PSR-20 clock, PSR-14 events | the framework's own services, when the container has them | `DateClock`, `DispatcherEvents` |

## The registry

`Omnibridge\Runtime` holds one service per contract, asked by the interface's name. A host fills it
once, at boot: with a PSR-11 container whose ids are those names (`setContainer()`: Laravel's
application, a Symfony service locator), or one service at a time (`set()`, which wins). A contract
asked and not filled throws `Omnibridge\Exception\MissingContractException` (never a silent null),
but the clock; `find()` answers null for a caller that can do without. A different container
forgets what the previous one gave; what was set by hand stays.

A contract is the same whatever name it is asked by. A family keeps a former name with
`Runtime::alias($contract, $former)` (a `class_alias`, and the registry told), declared when
Composer's autoloader is loaded: a service set or bound under either name is the same object, and
the container is asked by the own name, then by the name asked, then by the former names - a host
that still binds only a former name is found by the new one. `formerNames()` lists them; a missing
contract's exception names them.

The registry is not final: an application family adds its own contracts' accessors in a class of
its own over the same state, as omnisense/omnibase's `Base\Contract\Runtime` does. The whole API is
in [The registry](registry.md).

## The data structure: `Omnibridge\Schema`

The structure of an application's data - tables, columns and their types, indexes and unique
constraints, relations and their join columns, hierarchies and their discriminators, the names the
naming gives - described once, outside any ORM (the bridge principle of 2026-10-19: several
persistence techniques, the same tables), so that Doctrine reads it today and another persistence
(Eloquent or other) maps the same tables from it tomorrow.

- `Schema\Declaration\*`: what the entity classes declare, read from their attributes by reflection
  and **without instantiating them** - the attributes are `Doctrine\ORM\Mapping`'s, the vocabulary
  omnibase's entities are written in, but their classes need not exist: `Vocabulary` holds each
  attribute's parameters and defaults as data (omnibridge/doctrine's `VocabularyTest` keeps it equal
  to doctrine/orm's constructors). `AttributeReader` reads a class into a `ClassDeclaration` (its
  attributes, its properties with the class that declares each, its lifecycle methods);
  `DeclarationSet` keeps declarations as data (a JSON file, `toArray()`/`fromArray()`). A
  persistence bridge maps the classes from a `Declarations` source and never reads the attributes
  itself.
- `Schema\Description`: the neutral description - `Entity` (table, kind, parent and root,
  `Inheritance`, identifier, `Field`s, `Relation`s with their `JoinColumn`s or `JoinTable`), and the
  `Table`s they make (`Column`s with their portable type, primary key, `Index`es, `ForeignKey`s).
  Plain data too (`toJson()`/`fromJson()`).
- `Schema\Builder`: builds the description from the declarations alone, the way Doctrine completes
  its metadata (a parent's fields inherited, a type guessed from the property's, default join
  columns and join tables, default discriminator maps) and the way its SchemaTool makes the tables
  (one table per joined class, one for a single-table hierarchy, a join table per owning
  many-to-many, the implicit indexes behind foreign keys, DBAL's generated names).
  `Builder\Rule` is an application's own convention, applied at the three moments an ORM gives its
  listeners (before the declaration is read, once read, once the class is whole): omnibase's
  translations, discriminator entries, vaults and order columns are rules in omnisense/omnibase.
- `Schema\Naming` / `CamelCaseNaming`: how the unnamed is named (omnibase's camel case:
  `App\Entity\Thread\Tag` is `threadTag`, `Thread::$tags` the join table
  `thread_tags (thread_id, threadTag_id)`), and a generated index or foreign key name as DBAL
  generates it. omnibridge/doctrine's `NamingStrategy` answers Doctrine with it.
- `Schema\Database\MySqlSchema`: a MySQL schema read from `information_schema` (PDO, read-only) and
  compared with a description, column by column - the independent proof that the description is the
  database.

```php
use Omnibridge\Schema\Builder;

$description = (new Builder(rules: $applicationRules))->build($entityClasses);
$description->table('thread')->column('slug');          // string(255), not null
$description->entity(Thread::class)->relation('tags');   // many_to_many, join table thread_tags
file_put_contents('schema.json', $description->toJson());
```

omnibridge/doctrine reads the same declarations into Doctrine's metadata (`DeclarationDriver`) and
writes an entity manager's metadata back in this form (`MetadataDescription`): the two descriptions
are compared in its tests and on each site (omnibase's harness, lesringostars), and the site's with
its database's `information_schema`. Everything about it is in [The schema](schema.md).

## What stays with omnisense/omnibase

The contracts that describe omnibase's own models, not a host, stay `Base\Contract\*` in
omnisense/omnibase:

| Contract | Why it is omnibase's |
|---|---|
| `Nouns` | what an omnibase entity is called, by omnibase's rule over its "entities" catalogues (namespaces, politeness, gender) |
| `Media` | omnibase's image route (`ux_image`) and its obfuscated `data` |
| `Pictures` | omnibase's `\|picture` filter: a stored image at a size, by omnibase's thumbnails |
| `Texts` | omnibase's stored texts (Editor.js, Quill) and their `\|wysiwyg` rendering |
| `Base\Screen\ContextProvider` | the frame of omnibase's screens (site, links, viewer, theme) |
| `SecurityUser`, `TwoFactorUser`, `Framework` | what omnibase's `User` declares to a framework's security, and the rule that decides it |
| `Link` | a helper over `Urls` with Symfony's reference types, for omnibase's entities' `__toLink()` |
| `Runtime::parameters()`, `::mailer()` | omnibase's parameter bag (database settings over the configuration), symfony/mailer's interface |

## Former names

Each contract and class here was moved from another package, and kept reachable under its former
name by `Runtime::alias()` (a `class_alias`: an alias, not a subclass - one interface, one
reflection name), declared by the package that owned the name, eagerly (Composer's `files`),
until omnibase 4's final pass:

| Here | Former names |
|---|---|
| `Omnibridge\Contract\Urls` | `Base\Contract\Urls`, `Omnibridge\Twig\Contract\Urls` |
| `Omnibridge\Contract\Translations` | `Base\Contract\Translations`, `Omnibridge\Twig\Contract\Translations` (which asked `trans()` alone) |
| `Omnibridge\Contract\{Locales, Access, Sessions, Tokens, Files, Routes, Environment}` | `Base\Contract\{...}` |
| `Omnibridge\SystemClock` | `Base\Contract\SystemClock` |
| `Omnibridge\Exception\MissingContractException` | `Base\Exception\MissingContractException` |

That pass is done: omnisense/omnibase removed its aliases (commit 02682623, its
`src/Resources/Aliases.php`) and omnibridge/twig its own (commit 976de23), once no site and no
package named them. Name the classes of the left column. No package declares a former name today;
the mechanism stays, for the next move ([The registry](registry.md#former-names)).

`Base\Contract\Runtime` was never an alias: it is omnibase's registry over this one, with omnibase's
own accessors.

> [!NOTE]
> The README's "Former names" section still says the aliases live "until omnibase 4's final pass",
> and `SystemClock`'s and `MissingContractException`'s docblocks that the former name "remains its
> alias": both were written before the pass.

## How this manual is organised

- [Installation](installation.md): requirements, a plain PHP script and its output, the hosts.
- [Contracts](contracts.md): each interface, every method, what a bridge must answer.
- [The registry](registry.md): `Runtime` method by method - filling it, asking it, former names,
  a family's own registry.
- [The schema](schema.md): declarations, vocabulary, the builder and its rules, the description's
  classes field by field, the naming, the MySQL comparison.
- [Errors](errors.md): every exception and when it is thrown.
- [Testing](testing.md): testing code that asks the registry, the package's own suite and its
  Docker harness.
- [Verified, and not](verified.md).

## Licence

MIT.
