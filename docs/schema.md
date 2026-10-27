---
title: The schema
order: 35
---

# The schema

`Omnibridge\Schema` describes the structure of an application's data once, outside any ORM: the
entities - each class, its table, its fields and relations, its inheritance - and the tables they
make, with their columns, keys, indexes and foreign keys. Three steps:

1. **Declarations** (`Schema\Declaration\*`): what the entity classes declare, read from their
   attributes without instantiating them, or kept as data.
2. **The builder** (`Schema\Builder`): the declarations completed the way Doctrine completes its
   metadata, an application's rules applied, then the tables Doctrine's SchemaTool would create.
3. **The description** (`Schema\Description`): plain, readonly data - entities and tables - that a
   persistence bridge maps, a JSON file keeps, and `Schema\Database\MySqlSchema` compares with a
   database.

omnibridge/doctrine is the Doctrine side: its `DeclarationDriver` gives Doctrine its metadata from the
same declarations, its `MetadataDescription` reads an entity manager's metadata back into a
`Description`, and its tests compare the two.

## A complete example

Two entities, a many-to-one and a many-to-many relation, described with no ORM installed (the
`Doctrine\ORM\Mapping` attribute classes need not exist):

```php
<?php

namespace App\Entity {
    use Doctrine\ORM\Mapping as ORM;

    #[ORM\Entity]
    class Thread
    {
        #[ORM\Id, ORM\GeneratedValue, ORM\Column]
        public ?int $id = null;

        #[ORM\Column(unique: true)]
        public string $slug;

        #[ORM\Column(nullable: true)]
        public ?\DateTimeImmutable $publishedAt = null;

        #[ORM\ManyToOne]
        public ?Thread\Tag $mainTag = null;

        #[ORM\ManyToMany(targetEntity: Thread\Tag::class)]
        public iterable $tags = [];
    }
}

namespace App\Entity\Thread {
    use Doctrine\ORM\Mapping as ORM;

    #[ORM\Entity]
    class Tag
    {
        #[ORM\Id, ORM\GeneratedValue, ORM\Column]
        public ?int $id = null;

        #[ORM\Column(length: 64)]
        public string $name;
    }
}

namespace {
    require 'vendor/autoload.php';

    use Omnibridge\Schema\Builder;

    $description = (new Builder())->build([App\Entity\Thread::class, App\Entity\Thread\Tag::class]);

    foreach ($description->tables as $table) {
        echo $table->name, ' (', implode(', ', $table->primaryKey), ")\n";
        foreach ($table->columns as $column) {
            echo '  ', $column->name, ' ', $column->type, $column->length ? '('.$column->length.')' : '', $column->nullable ? ' null' : ' not null', $column->autoincrement ? ' auto_increment' : '', "\n";
        }
        foreach ($table->indexes as $index) {
            echo '  index ', $index->name, $index->unique ? ' unique' : '', ' (', implode(', ', $index->columns), ")\n";
        }
        foreach ($table->foreignKeys as $key) {
            echo '  foreign key ', $key->name, ' (', implode(', ', $key->columns), ') -> ', $key->table, ' (', implode(', ', $key->referencedColumns), ')', $key->onDelete ? ' on delete '.$key->onDelete : '', "\n";
        }
    }

    $tags = $description->entity(App\Entity\Thread::class)->relation('tags');
    echo $tags->kind, ' ', $tags->target, ' join table ', $tags->joinTable->name, "\n";
    echo json_encode($description->table('thread')->column('slug')->toArray()), "\n";
}
```

Its output (the package's harness image, PHP 8.2.34, 2026-10-27):

```text
thread (id)
  id integer not null auto_increment
  slug string(255) not null
  publishedAt datetime_immutable null
  mainTag_id integer null
  index IDX_31204C83941A39FD (mainTag_id)
  index UNIQ_31204C83989D9B62 unique (slug)
  foreign key FK_31204C83941A39FD (mainTag_id) -> threadTag (id)
threadTag (id)
  id integer not null auto_increment
  name string(64) not null
thread_tags (thread_id, threadTag_id)
  thread_id integer not null
  threadTag_id integer not null
  index IDX_EB0D88A83DC29580 (threadTag_id)
  index IDX_EB0D88A8E2904019 (thread_id)
  foreign key FK_EB0D88A83DC29580 (threadTag_id) -> threadTag (id) on delete CASCADE
  foreign key FK_EB0D88A8E2904019 (thread_id) -> thread (id) on delete CASCADE
many_to_many App\Entity\Thread\Tag join table thread_tags
{"name":"slug","type":"string","length":255}
```

What the builder completed: the column types from the properties' types (`int` integer,
`string` string of 255, `?\DateTimeImmutable` `datetime_immutable`), the table names by the camel
case naming (`App\Entity\Thread\Tag` is `threadTag`), the many-to-one's target from the property's
type and its join column `mainTag_id`, the join table `thread_tags` with a column toward each side
(on delete cascade), the auto-increment of an `AUTO` generated identifier, the unique index of the
unique column, the index behind each foreign key, and every generated name as DBAL generates it.
The plain form of a column leaves out what its constructor would give anyway (`nullable: false`).

## Declarations

### Reading the attributes: `AttributeReader`

```php
use Omnibridge\Schema\Declaration\AttributeReader;

$declaration = (new AttributeReader())->read(App\Entity\Thread::class);   // ?ClassDeclaration
```

`read(string|\ReflectionClass $class): ?ClassDeclaration` reads a class by reflection: each
attribute's name and arguments (`ReflectionAttribute::getArguments()`), never `newInstance()`, so the
attribute classes need not exist and doctrine/orm need not be installed.

- Only the attributes of the vocabulary are read (`Doctrine\ORM\Mapping\*` with a name listed in
  `Vocabulary::PARAMETERS`); every other attribute is ignored.
- The properties are read in the order reflection lists them, inherited ones included, each with
  the class that declares it; a property without a mapping attribute is left out. Of the methods,
  only public ones that carry a mapping attribute (the lifecycle callbacks).
- A positional argument is named after the parameter it fills (`#[ORM\Column('slug')]` is
  `name: 'slug'`); a nested attribute written as an object (`new ORM\JoinColumn(...)` inside a
  `JoinTable`) becomes an `Attribute` of its initialised properties, those equal to their default
  left out. That object is instantiated by PHP when the arguments are read: its class must exist.
- A constant written as an argument (`UtcDateTimeImmutableType::NAME`) is evaluated by PHP, so its
  class must load.
- A class that is neither `Entity`, `MappedSuperclass` nor `Embeddable` reads `null`. Each class is
  read once per reader.

### What a declaration holds

| Class | Public API | Meaning |
|---|---|---|
| `ClassDeclaration` | `$class`, `$attributes` (`list<Attribute>`), `$properties` (`list<Member>`), `$methods` (`list<Member>`) | what a class declares and nothing resolved: no default name, no type guessed |
| | `kind(): ?string` | `Entity`, `MappedSuperclass` or `Embeddable`, null for a class the mapping does not hold |
| | `attribute(string $name): ?Attribute` | the class attribute of that name, the last written |
| | `attributes(string $name): list<Attribute>` | every one of that name (a repeatable `Index`) |
| | `property(string $name): ?Member` | one property |
| `Member` | `$name`, `$class` (the declaring class), `$attributes` | a property or a method with its mapping attributes |
| | `attribute()`, `attributes()` | as above |
| `Attribute` | `$name` (`Column`, `ManyToOne`...), `$arguments` (by parameter name, as written) | one attribute |
| | `has(string $argument): bool` | whether it was written |
| | `get(string $argument): mixed` | as written, else the vocabulary's default |
| | `all(): array` | every parameter: as written, else its default (the required ones without a default left out) |

Each has `toArray()` and `fromArray()`: the plain form, where a nested attribute is
`{"@attribute": {"name": ..., "arguments": ...}}` and empty lists are left out.

### Keeping declarations as data: `DeclarationSet`

```php
use Omnibridge\Schema\Declaration\AttributeReader;
use Omnibridge\Schema\Declaration\DeclarationSet;

$set = DeclarationSet::of(new AttributeReader(), $entityClasses);
file_put_contents('declarations.json', json_encode($set->toArray()));

$again = DeclarationSet::fromArray(json_decode(file_get_contents('declarations.json'), true));
$builder = new Omnibridge\Schema\Builder($again);
```

`DeclarationSet` implements `Declarations` over a list of `ClassDeclaration`s: `of()` gathers the
declarations of classes from another source (the classes it does not map left out), `read()` answers
by class name, `all()` lists them by class, `toArray()`/`fromArray()` are the plain form. A set can
come from a file written once, or from another vocabulary turned into this one.

The builder still reflects on the classes themselves (their parents, their properties' types): a
`DeclarationSet` replaces the attributes, not the classes.

### The `Declarations` interface

```php
interface Declarations
{
    public function read(string|\ReflectionClass $class): ?ClassDeclaration;
}
```

Where the builder and omnibridge/doctrine's `DeclarationDriver` get the declarations from.
`AttributeReader` and `DeclarationSet` are the two implementations here.

### The vocabulary

`Vocabulary` holds the mapping attributes as data, kept equal to doctrine/orm 3's constructors
(omnibridge/doctrine's `VocabularyTest` compares them parameter by parameter):

| Constant or method | Meaning |
|---|---|
| `NAMESPACE` | `Doctrine\ORM\Mapping\` - the namespace whose attributes are read |
| `PARAMETERS` | attribute => parameter => default, in constructor order; `REQUIRED` for a parameter without a default |
| `REPEATABLE` | `Index`, `InverseJoinColumn`, `JoinColumn`, `UniqueConstraint` |
| `CALLBACKS` | the lifecycle attributes by the event they name: `PrePersist` => `prePersist`, `PostPersist`, `PreUpdate`, `PostUpdate`, `PreRemove`, `PostRemove`, `PostLoad`, `PreFlush` |
| `REQUIRED` | the marker `"\0required"` |
| `nameOf(string $className): ?string` | `Doctrine\ORM\Mapping\Column` => `Column`, null outside the vocabulary |
| `parameters(string $name): list<string>` | the parameters in constructor order |
| `defaults(string $name): array` | the optional parameters and their defaults |
| `defaultOf(string $name, string $parameter): mixed` | one default, null for a required parameter |
| `isRepeatable(string $name): bool` | |

The attributes it knows: `AssociationOverride`, `AssociationOverrides`, `AttributeOverride`,
`AttributeOverrides`, `Cache`, `ChangeTrackingPolicy`, `Column`, `CustomIdGenerator`,
`DiscriminatorColumn`, `DiscriminatorMap`, `Embeddable`, `Embedded`, `Entity`, `EntityListeners`,
`GeneratedValue`, `HasLifecycleCallbacks`, `Id`, `Index`, `InheritanceType`, `InverseJoinColumn`,
`JoinColumn`, `JoinColumns`, `JoinTable`, `ManyToMany`, `ManyToOne`, `MappedSuperclass`,
`OneToMany`, `OneToOne`, `OrderBy`, the eight lifecycle attributes, `SequenceGenerator`, `Table`,
`UniqueConstraint`, `Version`. All are read into declarations; the [builder](#what-the-builder-reads)
uses a subset of them.

## The builder

```php
use Omnibridge\Schema\Builder;
use Omnibridge\Schema\CamelCaseNaming;
use Omnibridge\Schema\Declaration\AttributeReader;

$builder = new Builder(
    declarations: new AttributeReader(),   // or a DeclarationSet
    naming: new CamelCaseNaming(),         // how the unnamed is named
    rules: [],                             // list<Builder\Rule>, the application's conventions
    autoGenerator: 'identity',             // what GeneratedValue(strategy: 'AUTO') becomes
);
$description = $builder->build($classNames);
```

| Constructor argument | Type | Default | Effect |
|---|---|---|---|
| `declarations` | `Declarations` | `new AttributeReader()` | where the declarations come from |
| `naming` | `Naming` | `new CamelCaseNaming()` | table, column, join column, join table and generated index names |
| `rules` | `list<Builder\Rule>` | `[]` | applied to each class, in order, at three moments |
| `autoGenerator` | `string` | `'identity'` | the generator an `AUTO` identifier becomes: `identity` is an auto-increment column (MySQL's) |

| Method | Returns | Meaning |
|---|---|---|
| `build(iterable $classNames)` | `Description` | the classes given that the declarations map (others skipped), each entity sorted by class name, then their tables; each call starts afresh |
| `classes()` | `list<string>` | the classes being described, in the order given (for a rule that needs the whole set) |
| `naming()` | `Naming` | |
| `declaration(string $className)` | `?ClassDeclaration` | a class's declaration, null for a class that does not exist |
| `draft(string $className)` | `Builder\Draft` | the class as described so far, its parents first: what an ORM's metadata factory hands out; built on demand, kept for the rest of the build |

### What the builder reads

Following Doctrine's `ClassMetadataFactory` and attribute driver, for each class, its mapped parents
first:

- **The parent's state**: inheritance type, discriminator column and map, generator, every field and
  relation (marked `inherited` from an entity parent, `declared` in the class that declares it),
  identifier, subclasses. A single-table child takes its root's table name, indexes, unique
  constraints and options; a mapped superclass's indexes are copied into its children.
- **The class**: `MappedSuperclass`, `Embeddable`; `Table` (`name`, backquotes stripped, and
  `options`); each `Index` and `UniqueConstraint` written on the class (`columns` or `fields`,
  `flags`, `options`, by name or by position); `InheritanceType`, `DiscriminatorColumn` (default
  `dtype`, `string`, 255) and `DiscriminatorMap`.
- **Each property** not inherited: a `Column` is a field (`type`, `length`, `precision`, `scale`,
  `unique`, `nullable`, `index`, `options`, `enumType`, `name` with backquotes stripped; `Id` and
  its `position`; `GeneratedValue`'s `strategy`); else a `OneToOne`, `OneToMany`, `ManyToOne` or
  `ManyToMany` is a relation (`targetEntity`, `mappedBy`, `inversedBy`, `indexBy`, `OrderBy`,
  `JoinColumn`s, `JoinTable` and `InverseJoinColumn`s, `Id`).
- **Completion** (`Draft::mapField()`, `mapRelation()`):
  - a field's type from its property's type when it names none: `\DateInterval` dateinterval,
    `\DateTime` datetime, `\DateTimeImmutable` datetime_immutable, `array` json, `bool` boolean,
    `float` float, `int` integer, `string` string; a backed enumeration its backing type, with
    `enumType` set (a pure enumeration is a `LogicException`); otherwise `string`;
  - a column name from the naming; a duplicate column (or one named as the discriminator) is a
    `LogicException`;
  - a to-one relation's target from its property's type when it names none; a target without a
    namespace is taken in the class's namespace; a relation without target is a `LogicException`;
  - the owning side: no `mappedBy`, and never a one-to-many;
  - an owning to-one's join columns: one by default, `<property>_id` toward `id`, nullable unless
    the relation is part of the identifier, unique for a one-to-one outside a single-table
    hierarchy;
  - an owning many-to-many's join table: its name from the naming, its join columns toward each
    side (`<table>_id`, not null, on delete `CASCADE` when the join table names none; a relation of
    a class to itself names them `<table>_source` and `<table>_target`);
  - the identifier in `Id` position order.
- **The generator**: a child of an entity keeps its root's; an `AUTO` root's becomes
  `autoGenerator`.
- **The default discriminator map**, for a root of a hierarchy that declares none: the class and
  each of its subclasses among the classes given, each under its short name in lower case.

Then the tables, following SchemaTool over DBAL: one table per class of a joined hierarchy (a
child's identifier its primary key and a foreign key to the root's table, on delete `CASCADE`, not
auto-increment), one table for a single-table hierarchy (the children's columns nullable, the
discriminator in it), the discriminator column in the root's table, a join table per owning
many-to-many (its primary key both sides' columns), a join column per owning to-one of its referenced
column's type, a unique index per unique column, an index per `index: true` column, the declared
indexes and unique constraints (a unique constraint replacing a plain index of the same columns),
an index behind each foreign key that no index of the same columns already backs (and dropped when
a later index fulfils it), an index the primary key spans dropped. A string column without a length
is 255 long. A `default` option is kept when it is a scalar (a backed enumeration's value).

> [!NOTE]
> Not described, so a description differs from Doctrine's tables when they are used: `Embedded`
> properties (no column for them; `Naming::embeddedFieldToColumnName()` is there but the builder
> never asks it), `AttributeOverrides` and `AssociationOverrides`, the `indexes` and
> `uniqueConstraints` arguments of `#[Table]` (write `#[Index]` and `#[UniqueConstraint]` on the
> class), `Table`'s `schema`, `SequenceGenerator` and `CustomIdGenerator`'s classes, and the
> collations, charsets and comments of columns. omnibase's entities use none of them.

### Rules

```php
interface Rule
{
    public function before(Draft $class, ClassDeclaration $declaration, Builder $builder): void;
    public function declared(Draft $class, ClassDeclaration $declaration, Builder $builder): void;
    public function loaded(Draft $class, ClassDeclaration $declaration, Builder $builder): void;
}
```

An application's own convention over what its classes declare - what an ORM's listeners add to its
metadata. Applied to each class, every rule in order, at the three moments an ORM gives its
listeners:

| Moment | State of the draft | Doctrine's equivalent |
|---|---|---|
| `before()` | the parent's state copied, the class's declaration not read yet | before the driver loads the class |
| `declared()` | the declaration read (fields, relations, table) | after `loadMetadataForClass()` |
| `loaded()` | the generator and the discriminator map settled: the class is whole | `loadClassMetadata` |

A rule changes the draft through its public properties and `mapField()`, `mapRelation()`,
`setDiscriminatorMap()`, `addSubClass()`, `setIdentifier()`; `$builder->draft()` gives another class
(a translation's parent), `$builder->classes()` the whole set. omnisense/omnibase's rules (its
translations' relations, its discriminator entries, its vaults' unique keys, its order columns) are
rules of this kind.

```php
use Omnibridge\Schema\Builder;
use Omnibridge\Schema\Builder\Draft;
use Omnibridge\Schema\Builder\Rule;
use Omnibridge\Schema\Declaration\ClassDeclaration;

/** Every entity gets a nullable `locale` column, unless it maps one. */
final class LocaleRule implements Rule
{
    public function before(Draft $class, ClassDeclaration $declaration, Builder $builder): void {}

    public function declared(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
        if (!$class->isMappedSuperclass && !$class->hasField('locale')) {
            $class->mapField(['fieldName' => 'locale', 'type' => 'string', 'length' => 5, 'nullable' => true]);
        }
    }

    public function loaded(Draft $class, ClassDeclaration $declaration, Builder $builder): void {}
}
```

Given to the [example](#a-complete-example)'s builder (`new Builder(rules: [new LocaleRule()])`), it
adds `locale string(5) null` to `thread` and to `threadTag` (run in the harness image, 2026-10-27).

### The draft

`Builder\Draft` is a class while it is built, its mappings in plain arrays:

| Property | Type | Meaning |
|---|---|---|
| `name` | `string` (readonly) | the class |
| `parentClasses` | `list<string>` | the entities above it, the nearest first (not the mapped superclasses) |
| `rootEntityName` | `string` | the root of its hierarchy (itself for a root) |
| `isMappedSuperclass`, `isEmbeddedClass` | `bool` | |
| `table` | `array{name, indexes?, uniqueConstraints?, options?}` | the table name defaults to the naming's |
| `inheritanceType` | `string` | `none` (`Draft::NONE`), `single_table` or `joined` |
| `discriminatorColumn` | `?array{name, type, length}` | |
| `discriminatorMap` | `array<string, string>` | value => class |
| `discriminatorValue` | `?string` | this class's value in the map |
| `subClasses` | `list<string>` | |
| `generatorType` | `string` | `none`, `auto`, `identity`, `sequence`, `custom`... |
| `identifier`, `identifierPositions` | `list<string>`, `array<string, int>` | |
| `fieldMappings` | `array<string, array>` | property => `{fieldName, columnName, type, length, precision, scale, nullable, unique, index, options, id, enumType, inherited, declared}` |
| `fieldNames` | `array<string, string>` | column => property |
| `associationMappings` | `array<string, array>` | property => `{fieldName, type, targetEntity, sourceEntity, mappedBy, inversedBy, isOwningSide, joinColumns, joinTable, orderBy, indexBy, id, inherited, declared}` |

Methods: `naming()`, `isInheritanceTypeNone()`, `isInheritanceTypeSingleTable()`,
`isInheritanceTypeJoined()`, `isRootEntity()`, `hasField()`, `hasAssociation()`, `mapField(array)`,
`mapRelation(array)`, `setDiscriminatorMap(array)`, `addSubClass(string)` (only a real subclass),
`setIdentifier(array $identifier, array $positions)`.

## The description

Every class of the description is `final`, readonly, and carries `toArray()` and `fromArray()`
(the `Exports` trait: each property by its name, a value equal to its constructor's default left
out, an enumeration by its value or name). `Description` adds `toJson()` (pretty printed, slashes and
Unicode unescaped, zero fractions kept, a final new line) and `fromJson()`; both throw
`\JsonException` on invalid JSON.

### `Description`

| Member | Meaning |
|---|---|
| `$entities` (`array<string, Entity>`) | by class, sorted by class name |
| `$tables` (`array<string, Table>`) | by name, sorted |
| `entity(string $class): Entity` | `\OutOfBoundsException` for a class it does not hold |
| `table(string $name): Table` | `\OutOfBoundsException` for a table it does not hold |

### `Entity`

| Property | Type | Meaning |
|---|---|---|
| `class` | `string` | |
| `table` | `?string` | its table; null for a mapped superclass or an embeddable |
| `kind` | `string` | `Entity::ENTITY` (`entity`), `MAPPED_SUPERCLASS` (`mapped_superclass`), `EMBEDDABLE` (`embeddable`) |
| `parent` | `?string` | the nearest entity above it |
| `root` | `?string` | the root of its hierarchy, null for the root itself or a class outside a hierarchy |
| `inheritance` | `?Inheritance` | how its hierarchy is stored, null without one |
| `identifier` | `list<string>` | the identifier's properties |
| `fields` | `array<string, Field>` | by property, inherited ones included |
| `relations` | `array<string, Relation>` | by property |
| `subclasses` | `list<string>` | the entities below it, sorted |

`field(string)` and `relation(string)` throw `\OutOfBoundsException` for a property that is none.

### `Field`

| Property | Type | Default | Meaning |
|---|---|---|---|
| `property` | `string` | | |
| `column` | `string` | | the column's name |
| `type` | `string` | | the portable type (`string`, `integer`, `datetime_immutable`, `json`... or an application's own, `thread_state`) |
| `nullable` | `bool` | `false` | |
| `length`, `precision`, `scale` | `?int` | `null` | |
| `unique` | `bool` | `false` | |
| `options` | `array` | `[]` | what else the column says: `default`, `unsigned`, `fixed`, `comment`, `charset`... |
| `id` | `bool` | `false` | part of the identifier |
| `generated` | `?string` | `null` | the identifier's generator (`identity`...), for an identifier field |
| `enum` | `?string` | `null` | the PHP enumeration its values are read into |
| `inheritedFrom` | `?string` | `null` | the entity it is inherited from; null for the entity's own, a mapped superclass's or a trait's |
| `declaredIn` | `?string` | `null` | the class that declares the property, when it is not the entity |

### `Relation`

| Property | Type | Default | Meaning |
|---|---|---|---|
| `property` | `string` | | |
| `kind` | `string` | | `Relation::ONE_TO_ONE`, `MANY_TO_ONE`, `ONE_TO_MANY`, `MANY_TO_MANY` (`one_to_one`...) |
| `target` | `string` | | the target class |
| `owning` | `bool` | | the side that keeps the link |
| `mappedBy` | `?string` | `null` | the other side's property, on the inverse side |
| `inversedBy` | `?string` | `null` | the other side's property, on the owning side |
| `joinColumns` | `list<JoinColumn>` | `[]` | an owning to-one's |
| `joinTable` | `?JoinTable` | `null` | an owning many-to-many's |
| `orderBy` | `array<string, string>` | `[]` | property => `ASC` or `DESC` (an enumeration's `Ascending`/`Descending` read as such) |
| `indexBy` | `?string` | `null` | the property a collection is keyed by |
| `id` | `bool` | `false` | part of the identifier |
| `inheritedFrom`, `declaredIn` | `?string` | `null` | as a field's |

`isToOne(): bool` is true for one-to-one and many-to-one.

`JoinColumn`: `name`, `referencedColumn` (default `id`), `nullable` (default `true`), `unique`
(`false`), `onDelete` (`?string`). `JoinTable`: `name`, `joinColumns` (toward the owner),
`inverseJoinColumns` (toward the target).

### `Inheritance`

`type` (`Inheritance::SINGLE_TABLE` `single_table` or `JOINED` `joined`), `column` (a `Column`: the
discriminator), `map` (value => class, sorted by value), `value` (this class's value).

### `Table`, `Column`, `Index`, `ForeignKey`

| Class | Properties |
|---|---|
| `Table` | `name`; `columns` (`array<string, Column>`, in the order the database holds them); `primaryKey` (`list<string>`); `indexes` (`array<string, Index>`, the unique ones among them, sorted by name); `foreignKeys` (`array<string, ForeignKey>`, sorted). `column(string): Column` throws `\OutOfBoundsException` |
| `Column` | `name`, `type` (portable), `nullable` (`false`), `length`, `precision`, `scale` (`?int`), `fixed` (`false`), `unsigned` (`false`), `autoincrement` (`false`), `default` (`string\|int\|float\|bool\|null`), `values` (`?list<string>`, an enumeration's or a set's, given by an application's rule; the builder never fills it) |
| `Index` | `name`, `columns` (in order), `unique` (`false`), `flags` (`list<string>`, lower case: `fulltext`, `spatial`) |
| `ForeignKey` | `name`, `columns`, `table` (the referenced one), `referencedColumns`, `onDelete` (`?string`, upper case), `onUpdate` (`?string`) |

## Naming

```php
interface Naming
{
    public function classToTableName(string $className): string;
    public function propertyToColumnName(string $propertyName, string $className): string;
    public function embeddedFieldToColumnName(string $propertyName, string $embeddedColumnName, string $className, string $embeddedClassName): string;
    public function referenceColumnName(): string;
    public function joinColumnName(string $propertyName, string $className): string;
    public function joinTableName(string $sourceEntity, string $targetEntity, string $propertyName): string;
    public function joinKeyColumnName(string $entityName, ?string $referencedColumnName): string;
    public function generatedName(string $table, array $columns, string $prefix): string;
}
```

The questions Doctrine's naming strategy asks, plus `generatedName()` (an index, unique index or
foreign key nobody named; prefix `idx`, `uniq` or `fk`). omnibridge/doctrine's `NamingStrategy`
answers Doctrine with a `Naming`, so one class says how a class becomes a table.

### `CamelCaseNaming`

The omni family's names. Not final; constructed with the namespaces a table name leaves out
(default `['App\\', 'Base\\']`).

| Question | Answer | Example |
|---|---|---|
| `classToTableName()` | the `#[Table(name: ...)]` the class carries (the last one that names one); else the prefixes off (again and again), the first `Entity\` off, the rest joined in camel case, a repeated word once, a trailing `Intl` kept | `App\Entity\Thread\Tag` `threadTag`; `App\Entity\User\Profile` `userProfile`; `Base\Entity\Layout\Widget\Slot` `layoutWidgetSlot`; `App\Entity\User\UserProfile` `userProfile` |
| `propertyToColumnName()` | the property, first letter lower case | `publishedAt` |
| `embeddedFieldToColumnName()` | `<property>_<column>` | `address_city` |
| `referenceColumnName()` | `id` | |
| `joinColumnName()` | `<property>_id` | `mainTag_id` |
| `joinTableName()` | `<source table>_<property as a table name>` (the target's table when the property is empty) | `Thread::$tags` `thread_tags` |
| `joinKeyColumnName()` | `<entity's table>_<referenced column or id>` | `threadTag_id` |
| `generatedName()` | the prefix, `_`, the CRC32 of the table and each column in hexadecimal, upper case, cut at 64 characters - DBAL's form | `IDX_31204C83941A39FD` |

Constants: `TABLE_NAME_SIZE` and `IDENTIFIER_SIZE` (64, MySQL's limit), `TABLE_I18N_SUFFIX`
(`Intl`). A table name longer than 64 characters is a `\LengthException` ("Table name will be
truncated for ...").

## Comparing with a database: `MySqlSchema`

`Schema\Database\MySqlSchema` reads a MySQL database's schema from `information_schema` (through
PDO, read-only, no ORM) and lists every difference with a description - an independent check that
the description is the database: nothing of the persistence that made the tables is asked.

```php
use Omnibridge\Schema\Database\MySqlSchema;

$pdo = new \PDO('mysql:host=database;dbname=app', 'app', $password);
$schema = MySqlSchema::read(
    $pdo,
    database: null,                                  // DATABASE() when null
    ignore: ['doctrine_migration_versions'],         // tables the description does not hold
    types: ['thread_state' => MySqlSchema::enumeration(...)],   // an application's own types
);
foreach ($schema->differences($description) as $difference) {
    echo $difference, "\n";      // "thread.slug: type "varchar(255)" / "varchar(191)""
}
```

| Method | Meaning |
|---|---|
| `read(\PDO $pdo, ?string $database = null, array $ignore = [], array $types = [])` | the base tables of the database (views left out): columns in ordinal order (`COLUMN_TYPE`, nullable, default, auto-increment), primary key, indexes (unique, `fulltext`/`spatial` flags), foreign keys (referenced table and columns, delete and update rules; `RESTRICT` and `NO ACTION` read as null) |
| `of(array $tables, array $types = [])` | a schema kept as data (`read()`'s `$tables`, written once to a file) or written by hand |
| `$tables` | the tables read, readonly |
| `differences(Description $description): list<string>` | every difference, one a line; none when the description is the database's schema |
| `columnType(Column $column): string` | the SQL type MySQL 8 writes for a column of the description |
| `sameType(string $expected, string $actual): bool` (static) | equal once an integer's display width is dropped (`tinyint(1)` and `tinyint`, `int(11)` and `int`) |
| `enumeration(Column $column)`, `set(Column $column)` (static) | `enum('A','B')`, `set('A','B')` of the column's `values`, for `$types` |

What `differences()` compares, and the lines it writes:

- tables on one side only: `<table>: a table of the description, not of the database`, or the
  reverse;
- the column lists: `<table>: columns [missing] missing, [more] more`;
- each column's SQL type, nullable, default (a boolean as `1`/`0`) and auto-increment:
  `<table>.<column>: <what> <expected> / <actual>`;
- the primary key; each index by name (columns, unique, flags) and each foreign key by name
  (columns, table, referenced columns, on delete, on update): `missing`, `more`, or
  `<expected> / <actual>`.

Collations, character sets and comments are not compared. The portable types and their SQL types:

| Portable type | MySQL 8 |
|---|---|
| `string`, `ascii_string` | `varchar(<length or 255>)`, `char(...)` when `fixed` |
| `text` | `tinytext` up to 255, `text` up to 65535, `mediumtext` up to 16777215, else (or no length) `longtext` |
| `integer`, `smallint`, `bigint` | `int`, `smallint`, `bigint`, ` unsigned` when `unsigned` |
| `boolean` | `tinyint(1)` |
| `float`, `smallfloat` | `double`, `float` |
| `decimal` | `decimal(<precision or 10>,<scale or 0>)`, ` unsigned` when `unsigned` |
| `datetime`, `datetime_immutable`, `datetimetz`, `datetimetz_immutable`, `utc_datetime`, `utc_datetime_immutable` | `datetime` |
| `date`, `date_immutable` | `date` |
| `time`, `time_immutable` | `time` |
| `json` | `json` |
| `guid` | `char(36)` |
| `binary` | `varbinary(<length or 255>)`, `binary(...)` when `fixed` |
| `blob` | `longblob` |
| `array`, `simple_array`, `object` | `longtext` |
| `dateinterval` | `varchar(255)` |
| anything else | `$types[<type>]($column)`, else an `\UnexpectedValueException` ("No SQL type for ... give it in $types") |

A run with no database, on a schema given as data (the harness image, 2026-10-27):

```php
<?php

require 'vendor/autoload.php';

use Omnibridge\Schema\Column;
use Omnibridge\Schema\Database\MySqlSchema;
use Omnibridge\Schema\Description;
use Omnibridge\Schema\Table;

$description = new Description([], [
    'page' => new Table('page', [
        'id' => new Column('id', 'integer', autoincrement: true),
        'title' => new Column('title', 'string', length: 120),
        'state' => new Column('state', 'page_state', values: ['draft', 'published']),
    ], ['id']),
]);

$database = MySqlSchema::of([
    'page' => [
        'columns' => [
            'id' => ['type' => 'int(11)', 'nullable' => false, 'default' => null, 'autoincrement' => true],
            'title' => ['type' => 'varchar(255)', 'nullable' => false, 'default' => null, 'autoincrement' => false],
            'state' => ['type' => "enum('draft','published')", 'nullable' => false, 'default' => null, 'autoincrement' => false],
        ],
        'primaryKey' => ['id'],
        'indexes' => [],
        'foreignKeys' => [],
    ],
    'doctrine_migration_versions' => ['columns' => [], 'primaryKey' => [], 'indexes' => [], 'foreignKeys' => []],
], ['page_state' => MySqlSchema::enumeration(...)]);

print_r($database->differences($description));
```

```text
Array
(
    [0] => doctrine_migration_versions: a table of the database, not of the description
    [1] => page.title: type "varchar(120)" / "varchar(255)"
)
```

`int(11)` matched `int` (the display width), the `enum` matched the application's type.
