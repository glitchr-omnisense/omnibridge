<?php

namespace Omnibridge\Tests\Schema;

use Omnibridge\Schema\Builder;
use Omnibridge\Schema\CamelCaseNaming;
use Omnibridge\Schema\Column;
use Omnibridge\Schema\Database\ColumnTypes;
use Omnibridge\Schema\Database\MySqlSchema;
use Omnibridge\Schema\Database\MySqlStatements;
use Omnibridge\Schema\Database\SchemaCreator;
use Omnibridge\Schema\Database\SchemaNotEmpty;
use Omnibridge\Schema\Database\SqliteSchema;
use Omnibridge\Schema\Database\SqliteStatements;
use Omnibridge\Schema\Description;
use Omnibridge\Schema\ForeignKey;
use Omnibridge\Schema\Index;
use Omnibridge\Schema\Table;
use Omnibridge\Tests\Fixtures\Schema\LocaleRule;
use PHPUnit\Framework\TestCase;

/**
 * The tables of a description created from the description alone (plan "omnibase agnostic", step F7a): the
 * statements for MySQL and for SQLite - tables, columns, indexes and unique indexes, foreign keys, join tables, a
 * joined and a single-table hierarchy with its discriminator -, run on SQLite and read back as the description, and
 * SchemaCreator's refusals.
 */
final class SchemaStatementsTest extends TestCase
{
    private static function fixtures(): Description
    {
        $classes = require __DIR__.'/../Fixtures/Schema/load.php';

        return (new Builder(naming: new CamelCaseNaming(['Omnibridge\\Tests\\Fixtures\\Schema\\']), rules: [new LocaleRule()]))->build($classes);
    }

    /** An application's own types: an enumeration, a set, an alias of a portable type. */
    private static function owned(): Description
    {
        return new Description([], [
            'page' => new Table('page', [
                'id' => new Column('id', 'integer', autoincrement: true),
                'state' => new Column('state', 'page_state', default: 'draft'),
                'roles' => new Column('roles', 'page_roles', nullable: true),
                'token' => new Column('token', 'page_token', length: 255),
                'visible' => new Column('visible', 'boolean', default: true),
                'rate' => new Column('rate', 'float', default: 1),
                'title' => new Column('title', 'string', length: 120, default: "it's"),
                'body' => new Column('body', 'text', nullable: true),
                'parent_id' => new Column('parent_id', 'integer', nullable: true),
            ], ['id'], [
                'IDX_PARENT' => new Index('IDX_PARENT', ['parent_id']),
                'FT_TITLE' => new Index('FT_TITLE', ['title', 'body'], flags: ['fulltext']),
            ], [
                'FK_PARENT' => new ForeignKey('FK_PARENT', ['parent_id'], 'page', ['id'], 'SET NULL'),
            ]),
        ]);
    }

    private static function types(): ColumnTypes
    {
        return new ColumnTypes(['page_token' => 'text'], ['page_state' => ['draft', 'published']], ['page_roles' => ['reader', 'editor']]);
    }

    public function testTheColumnTypesResolveAnAliasAnEnumerationAndASet(): void
    {
        $types = self::types();

        self::assertSame(['name' => 'token', 'type' => 'text', 'length' => 255], $types->resolve(new Column('token', 'page_token', length: 255))->toArray());
        self::assertSame(['name' => 'state', 'type' => 'enum', 'values' => ['draft', 'published']], $types->resolve(new Column('state', 'page_state'))->toArray());
        self::assertSame(['name' => 'roles', 'type' => 'set', 'nullable' => true, 'values' => ['reader', 'editor']], $types->resolve(new Column('roles', 'page_roles', nullable: true))->toArray());
        $column = new Column('slug', 'string', length: 64);
        self::assertSame($column, $types->resolve($column), 'a portable type is left as it is');
        self::assertSame('enum', (new ColumnTypes(['a' => 'b', 'b' => 'page_state'], ['page_state' => ['x']]))->resolve(new Column('c', 'a'))->type, 'an alias of an alias');
        self::assertSame('a', (new ColumnTypes(['a' => 'b', 'b' => 'a']))->resolve(new Column('c', 'a'))->type, 'a loop ends');

        self::assertEquals($types, ColumnTypes::fromArray(json_decode(json_encode($types->toArray()), true)), 'kept as data');
        self::assertSame(['page_token' => 'json'], $types->with(new ColumnTypes(['page_token' => 'json']))->aliases, 'the types given replace those of the same name');
        $mysql = $types->mysql();
        self::assertSame("enum('draft','published')", $mysql['page_state'](new Column('state', 'page_state')));
        self::assertSame("set('reader','editor')", $mysql['page_roles'](new Column('roles', 'page_roles')));
        self::assertSame('tinytext', $mysql['page_token'](new Column('token', 'page_token', length: 255)), 'MySqlSchema\'s types from the same data');
    }

    public function testTheMySqlStatements(): void
    {
        $statements = (new MySqlStatements(self::types()))->create(self::owned());

        self::assertSame([
            'CREATE TABLE `page` (`id` int AUTO_INCREMENT NOT NULL, `state` enum(\'draft\',\'published\') DEFAULT \'draft\' NOT NULL, `roles` set(\'reader\',\'editor\') DEFAULT NULL, '
                .'`token` tinytext NOT NULL, `visible` tinyint DEFAULT 1 NOT NULL, `rate` double DEFAULT 1 NOT NULL, `title` varchar(120) DEFAULT \'it\'\'s\' NOT NULL, `body` longtext DEFAULT NULL, '
                .'`parent_id` int DEFAULT NULL, INDEX `IDX_PARENT` (`parent_id`), FULLTEXT INDEX `FT_TITLE` (`title`, `body`), PRIMARY KEY (`id`)) '
                .'DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
            'ALTER TABLE `page` ADD CONSTRAINT `FK_PARENT` FOREIGN KEY (`parent_id`) REFERENCES `page` (`id`) ON DELETE SET NULL',
        ], $statements);

        self::assertStringEndsWith('DEFAULT CHARACTER SET latin1 ENGINE = MyISAM', (new MySqlStatements(self::types(), 'latin1', null, 'MyISAM'))->create(self::owned())[0], 'the table options given');
    }

    public function testTheFixturesOnMySqlEveryTableThenEveryForeignKey(): void
    {
        $description = self::fixtures();
        $statements = (new MySqlStatements())->create($description);

        self::assertCount(\count($description->tables) + array_sum(array_map(static fn (Table $table) => \count($table->foreignKeys), $description->tables)), $statements);
        self::assertSame(array_map(static fn (string $name) => 'CREATE TABLE `'.$name.'` (', array_keys($description->tables)), array_map(static fn (string $statement) => substr($statement, 0, strpos($statement, '(') + 1), \array_slice($statements, 0, \count($description->tables))), 'the tables first, by name');
        self::assertContains('CREATE TABLE `notice` (`id` int AUTO_INCREMENT NOT NULL, `message` longtext NOT NULL, `locale` varchar(5) NOT NULL, `kind` varchar(20) NOT NULL, `level` int DEFAULT NULL, '
            .'UNIQUE INDEX `notice_unique_translation` (`locale`), PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB', $statements, 'a single-table hierarchy: the discriminator, the subclass\'s column nullable');
        self::assertContains('CREATE TABLE `thread_tags` (`thread_id` int NOT NULL, `threadTag_id` int NOT NULL, INDEX `IDX_EB0D88A83DC29580` (`threadTag_id`), INDEX `IDX_EB0D88A8E2904019` (`thread_id`), '
            .'PRIMARY KEY (`thread_id`, `threadTag_id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB', $statements, 'a join table');
        self::assertContains('ALTER TABLE `article` ADD CONSTRAINT `FK_23A0E66BF396750` FOREIGN KEY (`id`) REFERENCES `thread` (`id`) ON DELETE CASCADE', $statements, 'a joined hierarchy: the subclass\'s identifier references the root\'s');
        self::assertContains('ALTER TABLE `user` ADD CONSTRAINT `FK_8D93D649CCFA12B8` FOREIGN KEY (`profile_id`) REFERENCES `profile` (`id`)', $statements);
        self::assertStringContainsString('`order` int DEFAULT NULL', implode("\n", $statements), 'a name SQL reserves, quoted');
        self::assertStringContainsString('`balance` decimal(10,2) NOT NULL, `score` int unsigned DEFAULT 0 NOT NULL', implode("\n", $statements));
    }

    public function testTheSqliteStatementsReadBackAsTheDescription(): void
    {
        foreach (['fixtures' => [self::fixtures(), new ColumnTypes()], 'own types' => [self::owned(), self::types()]] as $case => [$description, $types]) {
            $pdo = new \PDO('sqlite::memory:');
            $run = (new SchemaCreator($pdo, $types))->create($description);

            self::assertSame((new SqliteStatements($types))->create($description), $run, $case);
            $schema = SqliteSchema::read($pdo, [], $types);
            self::assertSame(array_keys($description->tables), array_keys($schema->tables), $case);
            self::assertSame([], $schema->differences($description), $case.': the database is the description');
        }

        $pdo = new \PDO('sqlite::memory:');
        (new SchemaCreator($pdo, self::types()))->create(self::owned());
        $page = SqliteSchema::read($pdo)->tables['page'];
        self::assertSame(['type' => 'INTEGER', 'nullable' => false, 'default' => null, 'autoincrement' => true], $page['columns']['id']);
        self::assertSame(['type' => 'CLOB', 'nullable' => false, 'default' => "'draft'", 'autoincrement' => false], $page['columns']['state'], 'an enumeration: a CLOB');
        self::assertSame(['type' => 'CLOB', 'nullable' => true, 'default' => 'NULL', 'autoincrement' => false], $page['columns']['roles']);
        self::assertSame(['type' => 'BOOLEAN', 'nullable' => false, 'default' => '1', 'autoincrement' => false], $page['columns']['visible']);
        self::assertSame(['FT_TITLE' => ['columns' => ['title', 'body'], 'unique' => false, 'flags' => []], 'IDX_PARENT' => ['columns' => ['parent_id'], 'unique' => false, 'flags' => []]], $page['indexes'], 'a fulltext index is a plain one on SQLite');
        self::assertSame(['FK_PARENT' => ['columns' => ['parent_id'], 'table' => 'page', 'referencedColumns' => ['id'], 'onDelete' => 'SET NULL', 'onUpdate' => null]], $page['foreignKeys'], 'the constraint\'s name read from the statement');
    }

    public function testADifferenceIsNamed(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        (new SchemaCreator($pdo, self::types()))->create(self::owned());
        $pdo->exec('CREATE TABLE "other" ("id" INTEGER NOT NULL)');
        $pdo->exec('DROP INDEX "IDX_PARENT"');

        $changed = new Description([], ['page' => new Table('page', self::owned()->table('page')->columns + ['extra' => new Column('extra', 'integer')], ['id'], self::owned()->table('page')->indexes, self::owned()->table('page')->foreignKeys)]);
        self::assertSame([
            'other: a table of the database, not of the description',
            'page: columns ["id","state","roles","token","visible","rate","title","body","parent_id","extra"] / ["id","state","roles","token","visible","rate","title","body","parent_id"]',
            'page: index IDX_PARENT {"columns":["parent_id"],"unique":false,"flags":[]} missing',
        ], SqliteSchema::read($pdo, [], self::types())->differences($changed));

        $first = new \PDO('sqlite::memory:');
        (new SchemaCreator($first, self::types()))->create(self::owned());
        self::assertSame([], SqliteSchema::read($first)->against(SqliteSchema::read($first)));
        self::assertSame(['other: a table of the second database only', 'page: index IDX_PARENT {"columns":["parent_id"],"unique":false,"flags":[]} missing'], SqliteSchema::read($first)->against(SqliteSchema::read($pdo)), 'two databases compared');
    }

    public function testMySqlSchemasComparedWithOneAnother(): void
    {
        $column = ['type' => 'int', 'nullable' => false, 'default' => null, 'autoincrement' => false];
        $one = MySqlSchema::of(['t' => ['columns' => ['a' => $column, 'b' => $column], 'primaryKey' => ['a'], 'indexes' => [], 'foreignKeys' => []]]);
        $two = MySqlSchema::of(['t' => ['columns' => ['b' => $column, 'a' => ['type' => 'bigint'] + $column], 'primaryKey' => ['a'], 'indexes' => [], 'foreignKeys' => []]]);

        self::assertSame([], $one->against($one));
        self::assertSame(['t: columns ["a","b"] / ["b","a"]', 't.a: type "int" / "bigint"'], $one->against($two), 'the columns\' order, then each property');
    }

    public function testTheCreatorRefusesADatabaseHoldingTablesOfTheDescription(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE "page" ("id" INTEGER NOT NULL)');
        $creator = new SchemaCreator($pdo, self::types());

        self::assertSame(['page'], $creator->existing(self::owned()));
        try {
            $creator->create(self::owned());
            self::fail('a table of the description exists');
        } catch (SchemaNotEmpty $refusal) {
            self::assertSame(['page'], $refusal->tables);
            self::assertStringContainsString('1 table of the description (page)', $refusal->getMessage());
        }
        self::assertSame(['page'], $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN), 'nothing was run');
        self::assertNotEmpty($creator->statements(self::owned()), 'what would run is still told');
    }

    public function testOnSqliteNothingIsLeftWhenAStatementFails(): void
    {
        $broken = new Description([], [
            'a' => new Table('a', ['id' => new Column('id', 'integer')], ['id'], ['IDX_SAME' => new Index('IDX_SAME', ['id'])]),
            'b' => new Table('b', ['id' => new Column('id', 'integer')], ['id'], ['IDX_SAME' => new Index('IDX_SAME', ['id'])]),
        ]);
        $pdo = new \PDO('sqlite::memory:');

        try {
            (new SchemaCreator($pdo))->create($broken);
            self::fail('SQLite refuses a second index of the same name');
        } catch (\PDOException) {
        }
        self::assertSame([], $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN), 'one transaction: no table left');
    }

    public function testAWriterPerDriver(): void
    {
        self::assertInstanceOf(MySqlStatements::class, SchemaCreator::writer('mysql'));
        self::assertInstanceOf(SqliteStatements::class, SchemaCreator::writer('sqlite'));
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No schema writer for PDO\'s "pgsql" driver');
        SchemaCreator::writer('pgsql');
    }

    public function testAnUnknownTypeIsRefusedAloud(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('No SQL type for "page_state" (column state): give it in the ColumnTypes.');
        (new SqliteStatements())->column(new Column('state', 'page_state'));
    }
}
