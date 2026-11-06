---
title: Errors
order: 50
---

# Errors

The package has one exception class of its own. The schema throws PHP's SPL exceptions, each with a
message that names what is wrong.

## `MissingContractException`

`Omnibridge\Exception\MissingContractException`, final, extends `\LogicException`. Formerly
`Base\Exception\MissingContractException`, an alias removed at omnibase 4's final pass. Two factories:

| Factory | Thrown by | Message |
|---|---|---|
| `for(string $contract, list<string> $formerNames = [])` | `Runtime::get()` and every accessor but `clock()`, when neither a service set by hand nor the container answers | `No <contract> (formerly <names>) in the runtime: the host fills it at boot (its omnibridge bridge, omnisense/omnibase's bundle under Symfony), or Omnibridge\Runtime::set() / setContainer() before it is asked.` |
| `notAn(string $contract, object $service)` | `Runtime::set()` with a service that does not implement the contract; `Runtime::find()`/`get()` when the container's service does not | `The runtime's <contract> is a <type>, which does not implement it.` |

It is a `LogicException` on purpose: a missing contract is a wiring mistake (a bridge not installed,
a package of SecurityBundle missing, a test that forgot `Runtime::set()`), not a condition to recover
from at run time. Code that can do without a contract asks `Runtime::find()` (null) or
`Runtime::has()` first, never catches the exception.

Under Symfony a contract whose framework service is absent is removed from the locator at compile
time (omnibridge/symfony's `ContractsPass`): with no SecurityBundle, `Runtime::access()` throws this
exception rather than a container error.

## The container's exceptions

`Omnibridge\Container` (see [the container](container.md)):

- `Omnibridge\Exception\ContainerException` (a `LogicException`, PSR-11's
  `ContainerExceptionInterface`): a constructor parameter that has no argument given, no
  `#[Autowire]`, no service of its type, no default and does not allow null (the message names the
  service, the class and the parameter); a cycle of services or of aliases; a class that cannot be
  instantiated; a parameter that is not a scalar written inside a string.
- `Omnibridge\Exception\ServiceNotFoundException` (a `ContainerException`, PSR-11's
  `NotFoundExceptionInterface`): no service of that id and nothing to autowire (an interface nobody
  answers), or no parameter of that name.

## The schema's exceptions

| Exception | Thrown by | When |
|---|---|---|
| `\LogicException` | `Builder` | `No declaration for <class>.`: a class in a hierarchy that the declarations do not map (an entity extending a class the declaration source does not hold) |
| `\LogicException` | `Draft::mapField()` | `Duplicate column "<column>" in <class>.`: two fields on one column, or a field on the discriminator column |
| `\LogicException` | `Draft::mapRelation()` | `No target for <class>::$<property>.`: a relation that names no target and whose property's type gives none |
| `\LogicException` | `Draft::mapField()` | `<class>::$<property>: a backed enumeration is required.`: a field typed by a pure enumeration and naming no type |
| `\LogicException` | `Builder\Tables` | `No column "<column>" in <class>.`: a join column referencing a column its target does not map |
| `\LengthException` | `CamelCaseNaming::classToTableName()` | `Table name will be truncated for "<class>"`: more than 64 characters |
| `\OutOfBoundsException` | `Description::entity()`, `::table()`, `Entity::field()`, `::relation()`, `Table::column()` | a class, table, property or column the description does not hold |
| `\UnexpectedValueException` | `MySqlSchema::columnType()`, so `differences()` | `No SQL type for "<type>" (column <name>): give it in $types.`: an application's own type not given |
| `\JsonException` | `Description::toJson()`, `fromJson()` | invalid JSON (`JSON_THROW_ON_ERROR`) |
| `\ReflectionException` | `AttributeReader::read()`, `Builder::build()` | a class name that does not exist |
| `\PDOException` | `MySqlSchema::read()`, `SqliteSchema::read()`, `SchemaCreator::create()` | whatever the connection throws in its error mode (on SQLite, nothing of the statements is left) |
| `Schema\Database\SchemaNotEmpty` | `SchemaCreator::create()` | `The database already holds <n> table(s) of the description (<names>): omnibridge:schema:create creates a schema in an empty database; ...`: nothing was run (`$tables`) |
| `\UnexpectedValueException` | `SqliteStatements::sqlType()`, so `create()` | `No SQL type for "<type>" (column <name>): give it in the ColumnTypes.` |
| `\DomainException` | `SchemaCreator::writer()`, `Cli::fromUrl()` | no writer for that PDO driver or URL scheme (mysql, mariadb and sqlite are written) |
| `\LogicException` | `MySqlStatements::table()`, `SqliteStatements::table()` | `The table <name> has no column.` |
| `\InvalidArgumentException`, `\UnexpectedValueException` | `Cli::schema()`, `Cli::connect()` | no schema file, a file returning neither a Description nor an array with `description`, no connection given |
