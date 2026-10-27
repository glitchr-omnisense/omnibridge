---
title: Verified, and not
order: 90
---

# Verified, and not

## What the tests prove

Run on 2026-10-27 in the package's image `omnibridge-core:php8.2` (PHP 8.2.34, PHPUnit 11.5.57), the
checkout's own `vendor/`, no network: 34 tests, 245 assertions, green. What each test class checks
is in [Testing](testing.md#the-packages-own-suite). In short:

- The registry: a missing contract is loud and named (with its former names), a service set by hand
  wins and must implement the contract, the container is asked once and forgotten when it changes,
  the clock falls back on the machine's, a former name is the same contract, a family's registry is
  the same state.
- The package stands alone: every class loads with the three PSR interfaces and nothing of a
  framework, an ORM, Twig or an application (`BareTest`).
- The schema without doctrine/orm installed: declarations read and read back, joined and
  single-table hierarchies, relations and their defaults, rules at their three moments, the tables
  as SchemaTool creates them, the naming, a MySQL schema compared (given as data).

Every example of this manual was run in the same image on 2026-10-27 and its output pasted: the plain
PHP registry script ([Installation](installation.md#plain-php)), the schema of two entities and the
rule ([The schema](schema.md#a-complete-example)), the naming examples, the MySQL comparison on data.

## Verified elsewhere, as the commits state

- The builder against Doctrine on omnisense/omnibase's harness (202 classes, `SchemaDescriptionTest`
  in omnibase compares the description built without Doctrine with Doctrine's on every class the
  harness maps): three differences found and fixed (commit e74e67c: `OrderBy` directions, quoted
  names).
- `MySqlSchema` on lesringostars' database (MySQL 8.4.11): the display width of integers found and
  ignored since (commit 4ce7ed1); nothing else differed.
- The registry under both hosts: omnibridge/symfony's and omnibridge/laravel's suites, and
  lesringostars' runs of `vendor/*/tests` (the registry's cases pass there, `BareTest` skipped).

## What is not verified

- `MySqlSchema::read()` is not run by this package's suite (no MySQL in the harness): its queries
  are checked on lesringostars' database only.
- The builder's behaviour for what omnibase's entities do not use: embedded classes, attribute and
  association overrides, sequence and custom generators, `Table`'s `indexes`/`uniqueConstraints`
  arguments ([The schema](schema.md#what-the-builder-reads) lists what is not described).
- A PHP without `ext-mbstring`: not tried; `CamelCaseNaming` would fail
  ([Installation](installation.md#requirements)).
- A third host (other than Symfony and Laravel): no bridge exists to try the contracts against.
