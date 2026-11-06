# Changelog

## [Unreleased]

### Added

- **`Persistence\Persistences` and `Exception\PersistenceException`** (omnisense/omnibase's plan "omnibase agnostic",
  step F7d; risk R8): the persistence found by what is installed - one bridge installed is used, both need the host's
  option (`base.persistence` under Symfony and Laravel, the parameter `persistence` in plain PHP) and are refused
  aloud without it; a name no persistence has, or one not installed, is refused naming the package to require.
  With both installed and the option naming none, the environment variable `OMNIBRIDGE_PERSISTENCE` may (a harness
  loading both).
  `installed()`, `isInstalled()`, `choose()`, `definitions()`, `register()` for another package's.
  **`Omnibridge\Container::fromDefinitions()`** chooses it the same way and adds its definitions first (omnibridge/
  eloquent's: unit of work, backend, repositories, omnibase's repository interfaces); the parameter `persistence` is
  then the name chosen. Docs: docs/persistence.md.

- **`Schema\Database\{Statements, MySqlStatements, SqliteStatements, ColumnTypes, SchemaCreator, SchemaNotEmpty,
  SqliteSchema, Cli}` and `bin/omnibridge-schema-create`** (omnisense/omnibase's plan "omnibase agnostic", step F7a: a
  new site's schema comes from its description, whatever the persistence): a description's tables created in an empty
  MySQL or SQLite database through PDO, no ORM - every table with its columns in order, primary key, indexes, unique,
  fulltext and spatial indexes, foreign keys (on MySQL after every table), join tables, the hierarchies' tables and
  discriminators as the description holds them; the SQL types, defaults and options DBAL 4 writes, so the database reads
  back as the one Doctrine's SchemaTool makes (omnisense/omnibase's SchemaDescriptionTest: 0 differences on lesringostars'
  132 tables on MySQL, on the harness's on SQLite). An application's own column types as data (`ColumnTypes`: aliases,
  enumerations, sets). `SchemaCreator` refuses a database holding a table of the description (`SchemaNotEmpty`), runs
  SQLite's statements in one transaction, and on MySQL takes a null collation as the database's own. `SqliteSchema`
  reads an SQLite database in `MySqlSchema`'s shape. The plain-PHP command (`Cli`): `--schema` (a PHP or JSON file),
  `--dsn`/`--url`/`DATABASE_URL`, `--database`, `--dump-sql`. omnibridge/symfony and omnibridge/laravel run the same
  `SchemaCreator` as `omnibridge:schema:create`. Docs: docs/schema.md.
- **`MySqlSchema::against()`** (and `compare()`): two databases read compared - tables, columns in their order, every
  column property, primary keys, indexes, foreign keys.

- **`Container\Discovery`** (omnisense/omnibase's plan "omnibase agnostic", step F5e slice 4c): what a host discovers among
  services declared as data, declared as data too - `autoconfigure` (the tags an autoconfigured definition's class gets,
  `tags()`, `autoconfigure()`), `collect` (the tagged services, or those a class names, handed to a collecting service,
  `collected()`), `fallbacks` (the first candidate defined, else a default class, `fallback()`), `preferred`; and
  `attributes()` (each service's tags of one name with their attributes). **`Omnibridge\Container::fromDefinitions()`**
  takes such a registry (fourth argument): the autoconfigured tags, the collections' calls, the fallbacks.
  `Declarations::resolve()` gives each definition's `autoconfigure` (its own or its parent's). omnisense/omnibase's
  `Base\Discovery` is one: omnibridge/symfony compiles it in one compiler pass, omnibridge/laravel applies it.
  Docs: docs/container.md.

- **`Options\{Processor, InvalidOption, Placeholders}`** (omnisense/omnibase's plan "omnibase agnostic", step F5e slice 4b):
  a configuration checked against a schema declared as data - types, defaults, deprecations, bounds, enumerations,
  sections completed with their defaults, lists and maps of a prototype (key attribute, default children, no deep
  merging), several configurations merged in their order - with the values, refusals (word for word) and deprecations
  Symfony's Config component gives the same schema compiled into a tree (omnibridge/symfony's `Options\SchemaTree`);
  a default's placeholders resolved by the host. omnisense/omnibase's `Base\Options` declares its `base:` options in
  this format; omnibridge/laravel validates `config/base.php` with it. Docs: docs/options.md.

- **`Container\Declarations`** (omnisense/omnibase's plan "omnibase agnostic", step F5e slice 4a): services declared as
  data, read the way every host reads them - `applies()` (requires, environment), `id()`, `contracts()` (a list item or
  an alias with its options), `position()`, `resolve()` (parents inherited as Symfony resolves a child definition, the
  abstract ones left out, no class loaded), `loadable()`, `tagged()`. **`Omnibridge\Container`** reads it:
  `fromDefinitions()` takes arguments by position, bindings, calls (one naming a service that may be ignored dropped),
  factories, tags (`tagged()`, `['!tagged_iterator' => tag]`), decorations (`'@.inner'`), `['!service_closure' =>
  '@id']`, `['!closure' => ...]`, `['!new' => ...]`, `%env(bool:NAME)%` and an `env(NAME)` default; a class not
  installed (or abstract) is not a service, asked only when the service is; a service Symfony's #[Autowire] names that
  the container lacks leaves the parameter to its type; `ids()`. omnisense/omnibase's whole declaration (192
  definitions) builds with it alone (its BareTest). Docs: docs/container.md.

- **`Form\{FieldBehaviour, FieldAssets, Endpoint, Coverage, UnsupportedKind}`** (omnisense/omnibase's plan "omnibase agnostic", step
  F5d): the contract one field behaviour answers on every host - its options and defaults, its widget's vars, the value shown and
  read back, its front end, the host's routes it calls - and a host's coverage, every kind implemented or unsupported with the
  reason (`Coverage::verify()`); a renderer refuses a kind it does not draw (`UnsupportedKind`) instead of a plain input.
  omnisense/omnibase's `Base\Field\Behaviour\*` implement the behaviours. Docs: docs/forms.md.
- **`Persistence\Snapshots`** (plan "omnibase agnostic", step F5c, item 3): what a unit of work remembers of an entity,
  `original(object $entity)` - its fields and to-one associations as read or last flushed, `[]` for an entity it does not
  manage. Implemented beside a backend's UnitOfWork (omnibridge/doctrine's DoctrineUnitOfWork); omnisense/omnibase's
  EntityHydrator reads it in place of Doctrine's unit of work.
- **`Contract\Roles`** (plan "omnibase agnostic", step F5c, item 3): the roles some roles reach in the host's hierarchy,
  `reachable(array $roles)`; `Runtime::roles()`. Symfony's role hierarchy (omnibridge/symfony's HierarchyRoles), Laravel's
  `role_hierarchy` (omnibridge/laravel's Auth\Roles); omnisense/omnibase's SecurityPolicy reads it without Security's
  RoleHierarchyInterface.
- **`Contract\Requests`** (plan "omnibase agnostic", step F5c): the request being answered, read without the framework's
  request object - the visitor's address, a header, the address asked, its host, the route, an attribute, the session's values
  (`session()`, `remember()`, `forget()`) and its flash messages (`flash()`, `flashes()`); `request()` hands the host's own object
  to code that still reads it. `Runtime::requests()`. Symfony's is omnibridge/symfony's StackRequests, Laravel's omnibridge/laravel's
  LaravelRequests; outside a request `has()` is false and nothing is read or written.
- **`Event\NamedEvent`, `Event\Events::dispatch()`**: an event known by a name as well as its class (omnibase's user.register),
  dispatched through any PSR-14 dispatcher - Symfony's is given the name, any other the event alone, which carries it.

- **`Omnibridge\Validation\AttributeConstraints`**: the rules a class declares read as kept constraints whichever attributes name them, by the
  attribute's name, its class never loaded: omnibridge's own, symfony/validator's (NotBlank, Length, Email, Regex, Choice, Range, Count, its
  UniqueEntity), an application's (`register()`). One rule on one line of a model, every host's Validation answers it. `Constraint\Count`.
  `Unique` on the values a form holds (an array, its class named).

- **The kept constraints and a plain-PHP validator** (plan "omnibase agnostic", step F5b, Marco's answer 4:
  forms everywhere): `Omnibridge\Validation\Constraint\{NotBlank, Length, Email, Regex, Choice, Range, File,
  Unique}`, plain objects and attributes with Symfony's messages and codes, and
  `Omnibridge\Validation\Validator`, the `Contract\Validation` of plain PHP for them (an object against its
  attributes, groups, plural forms, a translator callable, `Unique` through an `$exists` lookup; an unknown
  constraint refused aloud). Docs: `docs/contracts.md`.
- **`Omnibridge\Container`** (plan "omnibase agnostic", step F5a, Marco's answer 3: the services stay
  available everywhere): a small PSR-11 container for plain PHP - shared services, objects given,
  factories, aliases, classes defined with their arguments by name or autowired from their
  constructors (an argument given, Symfony's `#[Autowire]` read by its name and never loaded, the type,
  the default, null), parameters (`%name%`, `%env(NAME)%`); `fromDefinitions()` builds it from a list of
  definitions as data (class, contracts, arguments), the list omnisense/omnibase's `Base\Services`
  gives, so Symfony, Laravel and plain PHP read the same. Refused aloud: `ContainerException`,
  `ServiceNotFoundException` (PSR-11's interfaces). Docs: `docs/container.md`.
- **`Contract\ViewSources`**: where a view engine finds a file by its name (an e-mail's inline
  picture), beside `Views`, optional.
- **The persistence's contracts** (`Omnibridge\Persistence\`, plan "omnibase agnostic", 2026-11-01,
  step F1): `UnitOfWork` (persist, remove, flush, refresh, contains, reference, transactional, isNew,
  identifier, className, changes, recompute), the `Lifecycle\*` events (PrePersist, PostPersist,
  PreUpdate with its changes, PostUpdate, PreRemove, PostRemove, PostLoad, PreFlush, OnFlush,
  PostFlush), `Scope` and `Backend` (name, features, unit of work, scopes by name): what an
  application asks of omnibridge/doctrine or omnibridge/eloquent, whichever is installed. No backend
  answers them yet. Docs: `docs/persistence.md`.
- **`Contract\Views`** (a view rendered by its name, whatever the view technology) and
  **`Contract\Validation`** (a value checked against constraints, `Contract\Violation` per broken
  one). `Runtime::views()`, `validation()`, `backend()`, `unitOfWork()`. Docs: `docs/contracts.md`.

### Changed

- `MySqlSchema::columnType()`: the serialized arrays (`array`, `simple_array`, `object`) take a text of their length
  as `text` does (DBAL's CLOB: `tinytext` to `longtext`), and `blob` likewise (`tinyblob` to `longblob`; `longblob`
  without a length, as before); `enum` and `set` with their `values` are written `enum(...)`, `set(...)`.

### Fixed

- README and docblocks: omnibridge/symfony is named the Symfony host (it was "next"); the former
  names (`Base\Contract\*`, `Omnibridge\Twig\Contract\*`, `Base\Contract\SystemClock`,
  `Base\Exception\MissingContractException`) are said removed at omnibase 4's final pass, where they
  were said to remain aliases; `Declarations` names omnibridge/doctrine's `DeclarationDriver` (it
  said `DescriptionDriver`).
- composer.json requires `ext-mbstring`, which the package calls (`mb_*` functions): a PHP built without it failed with an undefined function.
