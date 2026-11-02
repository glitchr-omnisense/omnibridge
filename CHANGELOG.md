# Changelog

## [Unreleased]

### Added

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

### Fixed

- README and docblocks: omnibridge/symfony is named the Symfony host (it was "next"); the former
  names (`Base\Contract\*`, `Omnibridge\Twig\Contract\*`, `Base\Contract\SystemClock`,
  `Base\Exception\MissingContractException`) are said removed at omnibase 4's final pass, where they
  were said to remain aliases; `Declarations` names omnibridge/doctrine's `DeclarationDriver` (it
  said `DescriptionDriver`).
- composer.json requires `ext-mbstring`, which the package calls (`mb_*` functions): a PHP built without it failed with an undefined function.
