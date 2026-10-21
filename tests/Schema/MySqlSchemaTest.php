<?php

namespace Omnibridge\Tests\Schema;

use Omnibridge\Schema\Column;
use Omnibridge\Schema\Database\MySqlSchema;
use Omnibridge\Schema\Description;
use Omnibridge\Schema\ForeignKey;
use Omnibridge\Schema\Index;
use Omnibridge\Schema\Table;
use PHPUnit\Framework\TestCase;

/** A description compared with a MySQL schema, column by column (the schema given as read() gives it). */
final class MySqlSchemaTest extends TestCase
{
    private static function description(): Description
    {
        return new Description([], [
            'thread' => new Table('thread', [
                'id' => new Column('id', 'integer', autoincrement: true),
                'slug' => new Column('slug', 'string', length: 255),
                'state' => new Column('state', 'thread_state'),
                'publishedAt' => new Column('publishedAt', 'datetime', nullable: true),
                'score' => new Column('score', 'integer', unsigned: true, default: 0),
                'hidden' => new Column('hidden', 'boolean', default: false),
            ], ['id'], ['UNIQ_SLUG' => new Index('UNIQ_SLUG', ['slug'], true)], []),
            'thread_tags' => new Table('thread_tags', [
                'thread_id' => new Column('thread_id', 'integer'),
                'tag_id' => new Column('tag_id', 'integer'),
            ], ['thread_id', 'tag_id'], ['IDX_T' => new Index('IDX_T', ['thread_id'])], ['FK_T' => new ForeignKey('FK_T', ['thread_id'], 'thread', ['id'], 'CASCADE')]),
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private static function database(): array
    {
        return [
            'thread' => [
                'columns' => [
                    'id' => ['type' => 'int', 'nullable' => false, 'default' => null, 'autoincrement' => true],
                    'slug' => ['type' => 'varchar(255)', 'nullable' => false, 'default' => null, 'autoincrement' => false],
                    'state' => ['type' => "enum('draft','published')", 'nullable' => false, 'default' => null, 'autoincrement' => false],
                    'publishedAt' => ['type' => 'datetime', 'nullable' => true, 'default' => null, 'autoincrement' => false],
                    'score' => ['type' => 'int unsigned', 'nullable' => false, 'default' => '0', 'autoincrement' => false],
                    'hidden' => ['type' => 'tinyint(1)', 'nullable' => false, 'default' => '0', 'autoincrement' => false],
                ],
                'primaryKey' => ['id'],
                'indexes' => ['UNIQ_SLUG' => ['columns' => ['slug'], 'unique' => true, 'flags' => []]],
                'foreignKeys' => [],
            ],
            'thread_tags' => [
                'columns' => [
                    'thread_id' => ['type' => 'int', 'nullable' => false, 'default' => null, 'autoincrement' => false],
                    'tag_id' => ['type' => 'int', 'nullable' => false, 'default' => null, 'autoincrement' => false],
                ],
                'primaryKey' => ['thread_id', 'tag_id'],
                'indexes' => ['IDX_T' => ['columns' => ['thread_id'], 'unique' => false, 'flags' => []]],
                'foreignKeys' => ['FK_T' => ['columns' => ['thread_id'], 'table' => 'thread', 'referencedColumns' => ['id'], 'onDelete' => 'CASCADE', 'onUpdate' => null]],
            ],
        ];
    }

    /** @return array<string, \Closure(Column): string> */
    private static function types(): array
    {
        return ['thread_state' => static fn (Column $column) => MySqlSchema::enumeration(new Column($column->name, $column->type, values: ['draft', 'published']))];
    }

    public function testNoDifferenceWhenTheDescriptionIsTheDatabasesSchema(): void
    {
        self::assertSame([], MySqlSchema::of(self::database(), self::types())->differences(self::description()));
    }

    public function testEveryDifferenceIsNamed(): void
    {
        $database = self::database();
        $database['thread']['columns']['slug']['type'] = 'varchar(191)';
        $database['thread']['columns']['score']['default'] = null;
        unset($database['thread']['columns']['hidden']);
        $database['thread']['columns']['extra'] = ['type' => 'int', 'nullable' => true, 'default' => null, 'autoincrement' => false];
        $database['thread']['indexes']['IDX_MORE'] = ['columns' => ['state'], 'unique' => false, 'flags' => []];
        $database['thread_tags']['foreignKeys']['FK_T']['onDelete'] = null;
        $database['thread_tags']['primaryKey'] = ['tag_id', 'thread_id'];
        $database['other'] = ['columns' => [], 'primaryKey' => [], 'indexes' => [], 'foreignKeys' => []];
        unset($database['thread_tags']['indexes']['IDX_T']);

        self::assertSame([
            'other: a table of the database, not of the description',
            'thread: columns ["hidden"] missing, ["extra"] more',
            'thread.slug: type "varchar(255)" / "varchar(191)"',
            'thread.score: default "0" / null',
            'thread: index IDX_MORE {"columns":["state"],"unique":false,"flags":[]} more',
            'thread_tags: primary key ["thread_id","tag_id"] / ["tag_id","thread_id"]',
            'thread_tags: index IDX_T {"columns":["thread_id"],"unique":false,"flags":[]} missing',
            'thread_tags: foreign key FK_T {"columns":["thread_id"],"table":"thread","referencedColumns":["id"],"onDelete":"CASCADE","onUpdate":null} / {"columns":["thread_id"],"table":"thread","referencedColumns":["id"],"onDelete":null,"onUpdate":null}',
        ], MySqlSchema::of($database, self::types())->differences(self::description()));
    }

    public function testTheSqlTypeOfEachPortableType(): void
    {
        $schema = MySqlSchema::of([], self::types());

        self::assertSame('varchar(100)', $schema->columnType(new Column('a', 'string', length: 100)));
        self::assertSame('char(2)', $schema->columnType(new Column('a', 'string', length: 2, fixed: true)));
        self::assertSame('longtext', $schema->columnType(new Column('a', 'text')));
        self::assertSame('text', $schema->columnType(new Column('a', 'text', length: 65535)));
        self::assertSame('tinyint(1)', $schema->columnType(new Column('a', 'boolean')));
        self::assertSame('bigint unsigned', $schema->columnType(new Column('a', 'bigint', unsigned: true)));
        self::assertSame('decimal(10,2)', $schema->columnType(new Column('a', 'decimal', precision: 10, scale: 2)));
        self::assertSame('datetime', $schema->columnType(new Column('a', 'utc_datetime_immutable')));
        self::assertSame('json', $schema->columnType(new Column('a', 'json')));
        self::assertSame("enum('draft','published')", $schema->columnType(new Column('a', 'thread_state')));
        self::assertSame("set('a','b''c')", MySqlSchema::set(new Column('a', 'x', values: ['a', "b'c"])));

        $this->expectException(\UnexpectedValueException::class);
        $schema->columnType(new Column('a', 'unknown_type'));
    }
}
