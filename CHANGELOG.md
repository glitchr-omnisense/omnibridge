# Changelog

## [Unreleased]

### Added

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
