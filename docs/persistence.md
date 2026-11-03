---
title: Persistence
order: 33
---

# Persistence

What an application asks of the persistence installed, whatever it is: Doctrine
(omnibridge/doctrine) or Eloquent (omnibridge/eloquent), under Symfony, Laravel or plain PHP. The
entities stay the application's plain PHP objects; the tables are described once
([The schema](schema.md)); each backend maps the same tables. Since omnibase's step F1
(2026-11-01, plan "omnibase agnostic"): the contracts come first, the backends answer them at the
next steps (Doctrine at F2-F3, Eloquent at F7), so an application swaps one for the other by what it
installs.

## UnitOfWork

The entities kept, as an application's code asks where it would otherwise speak to an entity
manager. `Runtime::unitOfWork()`.

| Method | Returns | Meaning |
|---|---|---|
| `persist(object $entity)` | `void` | kept at the next flush (inserted when new) |
| `remove(object $entity)` | `void` | deleted at the next flush |
| `flush()` | `void` | writes what changed since the last flush, the lifecycle events dispatched |
| `refresh(object $entity)` | `void` | read again, its unsaved changes lost |
| `contains(object $entity)` | `bool` | whether the unit of work manages it |
| `reference(string $class, mixed $id)` | `object` | the entity, not read until a field is asked |
| `transactional(callable $work)` | `mixed` | the work in a transaction: flushed and committed, or rolled back and thrown again |
| `isNew(object $entity)` | `bool` | whether it has no row yet |
| `identifier(object $entity)` | `mixed` | its identifier, field => value when composite, null when the database gives it |
| `className(object $entity)` | `string` | its class, a proxy or a lazy object seen through |
| `changes(object $entity)` | `array` | field => [before, after] since it was read or flushed |
| `recompute(object $entity)` | `void` | its changes computed again, for a listener that changed it during a flush |

A backend's UnitOfWork may also implement **`Snapshots`**: `original(object $entity)` gives the values
the entity held when it was read or last flushed, by property (its fields and to-one associations),
`[]` for an entity it does not manage. omnisense/omnibase's EntityHydrator reads it to rebuild an
entity as it was (`getOriginalEntity()`: a user approved since the last flush). Doctrine's is its
unit of work's original entity data (omnibridge/doctrine's DoctrineUnitOfWork).

## Lifecycle

The moments a backend dispatches through the host's PSR-14 dispatcher, at the moments Doctrine
dispatches its own: an application's behaviours (a slug, a timestamp, an upload, a seal) listen to
these, whichever backend runs.

| Event | When |
|---|---|
| `Lifecycle\PrePersist` | before a new entity is first written |
| `Lifecycle\PostPersist` | after it was inserted, its identifier given |
| `Lifecycle\PreUpdate` | before a changed entity is updated: `changes()`, `hasChanged()`, `setNewValue()` for a changed field |
| `Lifecycle\PostUpdate` | after it was updated |
| `Lifecycle\PreRemove`, `PostRemove` | before and after it is deleted |
| `Lifecycle\PostLoad` | after it was read and its fields set |
| `Lifecycle\PreFlush`, `OnFlush`, `PostFlush` | the flush's moments: before it computes, when it has computed (a listener may persist, remove, change and `recompute()`), after it wrote |

An entity's event (`EntityEvent`) carries `entity` and `unitOfWork`, and `recompute()`; a flush's
(`FlushEvent`) carries `unitOfWork`.

## Scope

A condition every query of some entities carries while it is enabled: the trashed rows left out,
the sealed ones hidden, a tenant's rows only (Doctrine's SQL filters, Eloquent's global scopes).

| Method | Returns | Meaning |
|---|---|---|
| `name()` | `string` | by which it is enabled and disabled |
| `appliesTo(string $class)` | `bool` | whether it applies to that entity |
| `criteria(string $class, array $parameters = [])` | `array` | field => value (a list for IN, null for IS NULL), combined with And |

## Backend

The persistence installed. `Runtime::backend()`.

| Method | Returns | Meaning |
|---|---|---|
| `name()` | `string` | `doctrine`, `eloquent` |
| `supports(string $feature)` | `bool` | `SECOND_LEVEL_CACHE`, `RESULT_CACHE`, `LAZY_ASSOCIATIONS`, `TRANSACTIONS`, or a name of its own |
| `unitOfWork()` | `UnitOfWork` | its unit of work |
| `enableScope(string $name, array $parameters = [])`, `disableScope()`, `isScopeEnabled()` | | the scopes by name |

## What stays with omnibase

The finder language (`findOneByEmailAndUsername()`...) is omnibase's: its parser reads a name into a
neutral query (`Base\Database\Repository\Finder\Query`), which each backend compiles. The
repositories' interfaces per entity (`Base\Repository\Threads`...) are omnibase's too.
