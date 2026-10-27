---
title: Testing
order: 60
---

# Testing

## Testing code that asks the registry

The registry is static state: a test sets what the code under test asks, and forgets it after.

```php
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Urls;
use Omnibridge\Runtime;
use PHPUnit\Framework\TestCase;

final class ArticleLinkTest extends TestCase
{
    protected function setUp(): void
    {
        Runtime::reset();
    }

    protected function tearDown(): void
    {
        Runtime::reset();
    }

    public function testTheLinkIsTheRoutesPath(): void
    {
        $urls = $this->createMock(Urls::class);
        $urls->method('path')->with('article', ['slug' => 'le-fuji'])->willReturn('/articles/le-fuji');
        Runtime::set(Urls::class, $urls);

        self::assertSame('/articles/le-fuji', (new Article('le-fuji'))->link());
    }

    public function testAnAdministratorSeesTheEditButton(): void
    {
        Runtime::set(Access::class, new class implements Access {
            public function isGranted(mixed $attribute, mixed $subject = null): bool { return 'ROLE_ADMIN' === $attribute; }
            public function getUser(): ?object { return new \stdClass(); }
        });

        self::assertTrue((new Article('le-fuji'))->isEditable());
    }
}
```

- `Runtime::reset()` forgets the container, the services set and those resolved; the former names
  stay.
- A frozen clock: `Runtime::set(Psr\Clock\ClockInterface::class, $clock)` with any PSR-20 clock
  (symfony/clock's `MockClock`, or an anonymous class).
- A service that does not implement the contract is refused at `set()`, so a mock must be of the
  interface.
- Inside a framework's functional test (Symfony's `KernelTestCase`, Laravel's `TestCase`), the
  bridge fills the registry when the kernel or the application boots; a new kernel is a new
  container, and the registry forgets the previous one's services by itself.

## Testing a description

A description is plain data: build it from the application's entities and assert on it, or keep it
as a JSON file and compare.

```php
$description = (new Omnibridge\Schema\Builder(rules: $rules))->build($classes);
self::assertSame('thread_tags', $description->entity(Thread::class)->relation('tags')->joinTable->name);
self::assertJsonStringEqualsJsonFile(__DIR__.'/schema.json', $description->toJson());
```

Against the real database, `MySqlSchema::read($pdo, ignore: [...])->differences($description)`
must be `[]` ([The schema](schema.md#comparing-with-a-database-mysqlschema)). omnibridge/doctrine adds
the comparison with Doctrine's own metadata.

## The package's own suite

```sh
docker compose run --rm test
```

The service `test` of `compose.yml`: the image `omnibridge-core:php8.2` (`docker/Dockerfile`: PHP
8.2-cli, the lowest this package declares, with git, unzip and Composer 2, no extension added), the
package mounted at `/package`, a named volume `composer-cache` for Composer's cache; it runs
`composer update` then `vendor/bin/phpunit`. On a checkout whose `vendor/` is already installed, the
image runs the suite with no network:

```sh
docker run --rm --network none -v "$PWD":/package:ro omnibridge-core:php8.2 vendor/bin/phpunit --do-not-cache-result
```

| Test class | What it checks |
|---|---|
| `RuntimeTest` | a contract not filled is an exception naming it; every typed accessor throws when empty, but the clock (`SystemClock`); a service set by hand is answered and must implement the contract; the container is asked once, by the contract's name; a service of the wrong type in the container is refused; a different container forgets what the previous gave and keeps what was set; the clock is the container's when it has one; each accessor answers its contract; a contract is the same under a former name (set, bound or asked by either); a missing contract is named with its former names; `reset()` forgets everything; a family's own registry is the same registry |
| `BareTest` | every class of `src/` loaded in a PHP process of its own with Composer's autoloader: no file from outside `src/` but `psr/clock`, `psr/container` and `psr/event-dispatcher`, no symbol of Symfony, Illuminate, Laravel, Doctrine, Twig, Base or App; and the report does see what it loads |
| `Schema\AttributeReaderTest` | the vocabulary is not installed (no doctrine/orm); a class's declaration, its kind and attributes; the properties with the class that declares each; the vocabulary answers for every attribute of the mapping; a declaration reads back from its plain form |
| `Schema\BuilderTest` | every class described, the mapped superclass without a table; a joined hierarchy, its default map, its mapped superclass's field inherited; the relations and their default join columns and join tables; a single-table hierarchy with its declared map and the rules at their moments; the tables as SchemaTool creates them; the same description from declarations kept as data; a description read back from its plain form |
| `Schema\CamelCaseNamingTest` | tables and columns; a class that names its table, the prefixes given; a name generated as DBAL generates it; a table name too long refused |
| `Schema\MySqlSchemaTest` | no difference when the description is the database's schema; an integer's display width is no difference; every difference named; the SQL type of each portable type |

`BareTest` is measured on this package's own vendor: run from an application's `vendor/*/tests`
(lesringostars does so), it says so and is skipped, while the registry's cases still run there.
