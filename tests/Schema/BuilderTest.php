<?php

namespace Omnibridge\Tests\Schema;

use Omnibridge\Schema\Builder;
use Omnibridge\Schema\CamelCaseNaming;
use Omnibridge\Schema\Declaration\AttributeReader;
use Omnibridge\Schema\Declaration\DeclarationSet;
use Omnibridge\Schema\Description;
use Omnibridge\Schema\Entity;
use Omnibridge\Schema\Relation;
use Omnibridge\Tests\Fixtures\Schema\Entity\Alert;
use Omnibridge\Tests\Fixtures\Schema\Entity\Article;
use Omnibridge\Tests\Fixtures\Schema\Entity\Notice;
use Omnibridge\Tests\Fixtures\Schema\Entity\Profile;
use Omnibridge\Tests\Fixtures\Schema\Entity\Tag;
use Omnibridge\Tests\Fixtures\Schema\Entity\Thread;
use Omnibridge\Tests\Fixtures\Schema\Entity\Timestamps;
use Omnibridge\Tests\Fixtures\Schema\Entity\User;
use Omnibridge\Tests\Fixtures\Schema\LocaleRule;
use Omnibridge\Tests\Fixtures\Schema\State;
use PHPUnit\Framework\TestCase;

/**
 * The description built from the entities' declarations alone, with no ORM
 * installed: the classes completed as Doctrine completes its metadata, the
 * tables as its SchemaTool creates them (omnibridge/doctrine's tests compare
 * the same classes with Doctrine itself).
 */
final class BuilderTest extends TestCase
{
    /** @var list<class-string> */
    private static array $classes;

    public static function setUpBeforeClass(): void
    {
        self::$classes = require __DIR__.'/../Fixtures/Schema/load.php';
    }

    private static function naming(): CamelCaseNaming
    {
        return new CamelCaseNaming(['Omnibridge\\Tests\\Fixtures\\Schema\\']);
    }

    private static function build(LocaleRule $rule = new LocaleRule()): Description
    {
        return (new Builder(naming: self::naming(), rules: [$rule]))->build(self::$classes);
    }

    public function testEveryClassIsDescribedTheMappedSuperclassWithoutATable(): void
    {
        $description = self::build();

        self::assertSame(self::$classes, array_values(array_intersect(self::$classes, array_keys($description->entities))), 'every class given');
        self::assertSame(array_keys($description->entities), self::sorted(array_keys($description->entities)), 'by class name');

        $timestamps = $description->entity(Timestamps::class);
        self::assertSame(Entity::MAPPED_SUPERCLASS, $timestamps->kind);
        self::assertNull($timestamps->table);
        self::assertSame(['createdAt'], array_keys($timestamps->fields));
        self::assertSame('datetime_immutable', $timestamps->field('createdAt')->type, 'the type the property\'s type gives');
    }

    public function testAJoinedHierarchyItsMapGeneratedAndItsMappedSuperclassInherited(): void
    {
        $description = self::build();
        $thread = $description->entity(Thread::class);
        $article = $description->entity(Article::class);

        self::assertSame('thread', $thread->table);
        self::assertNull($thread->parent);
        self::assertNull($thread->root);
        self::assertSame([Article::class], $thread->subclasses);
        self::assertSame('joined', $thread->inheritance->type);
        self::assertSame(['name' => 'class', 'type' => 'string', 'length' => 255], $thread->inheritance->column->toArray());
        self::assertSame(['article' => Article::class, 'thread' => Thread::class], $thread->inheritance->map, 'Doctrine\'s default map: the short names, lower case, sorted');
        self::assertSame('thread', $thread->inheritance->value);
        self::assertSame(['id'], $thread->identifier);
        self::assertSame(['createdAt', 'id', 'slug', 'state'], array_keys($thread->fields), 'the mapped superclass\'s field first');
        self::assertSame(['declaredIn' => Timestamps::class, 'inheritedFrom' => null], ['declaredIn' => $thread->field('createdAt')->declaredIn, 'inheritedFrom' => $thread->field('createdAt')->inheritedFrom]);
        self::assertSame(['id' => true, 'generated' => 'identity', 'type' => 'integer'], ['id' => $thread->field('id')->id, 'generated' => $thread->field('id')->generated, 'type' => $thread->field('id')->type]);
        self::assertTrue($thread->field('slug')->unique);
        self::assertSame(['string', State::class], [$thread->field('state')->type, $thread->field('state')->enum], 'a backed enumeration: its backing type, the enumeration kept');

        self::assertSame('article', $article->table);
        self::assertSame(Thread::class, $article->parent);
        self::assertSame(Thread::class, $article->root);
        self::assertSame('article', $article->inheritance->value);
        self::assertSame(['createdAt', 'id', 'slug', 'state', 'title'], array_keys($article->fields));
        self::assertSame(Thread::class, $article->field('slug')->inheritedFrom);
        self::assertSame(Timestamps::class, $article->field('createdAt')->declaredIn);
        self::assertNull($article->field('title')->inheritedFrom);
        self::assertSame([true, 100], [$article->field('title')->nullable, $article->field('title')->length]);
    }

    public function testTheRelationsAndTheirDefaultJoinColumnsAndJoinTables(): void
    {
        $description = self::build();
        $article = $description->entity(Article::class);
        $thread = $description->entity(Thread::class);

        $tags = $thread->relation('tags');
        self::assertSame([Relation::MANY_TO_MANY, Tag::class, true, 'threads'], [$tags->kind, $tags->target, $tags->owning, $tags->inversedBy]);
        self::assertSame('thread_tags', $tags->joinTable->name, 'the naming\'s join table');
        self::assertSame([['name' => 'thread_id', 'nullable' => false, 'onDelete' => 'CASCADE']], array_map(fn ($column) => $column->toArray(), $tags->joinTable->joinColumns));
        self::assertSame([['name' => 'threadTag_id', 'nullable' => false, 'onDelete' => 'CASCADE']], array_map(fn ($column) => $column->toArray(), $tags->joinTable->inverseJoinColumns), 'toward the table the target names');
        self::assertSame(Thread::class, $article->relation('tags')->inheritedFrom);

        $threads = $description->entity(Tag::class)->relation('threads');
        self::assertSame([false, 'tags', null], [$threads->owning, $threads->mappedBy, $threads->joinTable]);

        $author = $article->relation('author');
        self::assertSame([Relation::MANY_TO_ONE, User::class], [$author->kind, $author->target], 'the target the property\'s type gives');
        self::assertSame([['name' => 'author_id', 'nullable' => false, 'onDelete' => 'SET NULL']], array_map(fn ($column) => $column->toArray(), $author->joinColumns));

        $connexes = $article->relation('connexes');
        self::assertSame('article_connexes', $connexes->joinTable->name);
        self::assertSame(['article_source', 'article_target'], [$connexes->joinTable->joinColumns[0]->name, $connexes->joinTable->inverseJoinColumns[0]->name], 'a relation to the class itself');

        $profile = $description->entity(User::class)->relation('profile');
        self::assertSame([Relation::ONE_TO_ONE, true, 'user'], [$profile->kind, $profile->owning, $profile->inversedBy]);
        self::assertSame(['name' => 'profile_id', 'unique' => true], $profile->joinColumns[0]->toArray(), 'a one-to-one join column is unique');
        self::assertSame([false, 'profile', []], [$description->entity(Profile::class)->relation('user')->owning, $description->entity(Profile::class)->relation('user')->mappedBy, $description->entity(Profile::class)->relation('user')->joinColumns]);
    }

    public function testASingleTableHierarchyWithItsDeclaredMapAndTheRulesAtTheirMoments(): void
    {
        $rule = new LocaleRule();
        $description = self::build($rule);
        $notice = $description->entity(Notice::class);
        $alert = $description->entity(Alert::class);

        self::assertSame(['notice', 'notice'], [$notice->table, $alert->table], 'one table');
        self::assertSame('single_table', $alert->inheritance->type);
        self::assertSame(['alert' => Alert::class, 'notice' => Notice::class], $alert->inheritance->map);
        self::assertSame(['notice', 'alert'], [$notice->inheritance->value, $alert->inheritance->value]);
        self::assertSame(['name' => 'kind', 'type' => 'string', 'length' => 20], $notice->inheritance->column->toArray());
        self::assertSame(['id', 'message', 'locale'], array_keys($notice->fields), 'the rule\'s field, once the declaration is read');
        self::assertSame([5, Notice::class], [$alert->field('locale')->length, $alert->field('locale')->inheritedFrom], 'inherited as a declared one');
        self::assertSame(['id', 'message', 'locale', 'level'], array_keys($alert->fields));

        self::assertSame(['before '.Notice::class, 'declared '.Notice::class, 'loaded '.Notice::class], array_slice($rule->moments, 0, 3), 'the three moments, in order, the parent first');
        self::assertSame('before '.Alert::class, $rule->moments[3]);
        self::assertCount(3 * count(self::$classes), $rule->moments, 'once per class');
    }

    public function testTheTablesAsDoctrinesSchemaToolCreatesThem(): void
    {
        $description = self::build();

        self::assertSame(['article', 'article_connexes', 'notice', 'profile', 'thread', 'threadTag', 'thread_tags', 'user'], array_keys($description->tables), 'by name: the classes\', the join tables, none for the mapped superclass nor the single-table subclass');

        $thread = $description->table('thread');
        self::assertSame(['createdAt', 'id', 'slug', 'state', 'class'], array_keys($thread->columns), 'the discriminator column last');
        self::assertSame(['id'], $thread->primaryKey);
        self::assertSame(['name' => 'id', 'type' => 'integer', 'autoincrement' => true], $thread->column('id')->toArray(), 'the identity generator: auto-increment');
        self::assertSame(['name' => 'slug', 'type' => 'string', 'length' => 255], $thread->column('slug')->toArray(), 'a string without a length: 255');
        self::assertSame(['name' => 'class', 'type' => 'string', 'length' => 255], $thread->column('class')->toArray());
        self::assertSame(['UNIQ_31204C83989D9B62' => ['slug', true], 'idx_created' => ['createdAt', false]], array_map(fn ($index) => [$index->columns[0], $index->unique], $thread->indexes), 'the unique column\'s index named as DBAL names it; the mapped superclass\'s index');
        self::assertSame([], $thread->foreignKeys);

        $article = $description->table('article');
        self::assertSame(['title', 'author_id', 'id'], array_keys($article->columns), 'its own columns, its join columns, then the root\'s identifier');
        self::assertSame(['name' => 'id', 'type' => 'integer'], $article->column('id')->toArray(), 'not auto-increment below the root');
        self::assertSame(['name' => 'author_id', 'type' => 'integer'], $article->column('author_id')->toArray(), 'of the referenced column\'s type, not null as declared');
        self::assertSame(['id'], $article->primaryKey);
        self::assertSame(['FK_23A0E66BF396750' => ['id', 'thread', 'CASCADE'], 'FK_23A0E66F675F31B' => ['author_id', 'user', 'SET NULL']], array_map(fn ($key) => [$key->columns[0], $key->table, $key->onDelete], $article->foreignKeys));
        self::assertSame(['IDX_23A0E66F675F31B' => ['author_id']], array_map(fn ($index) => $index->columns, $article->indexes), 'an index backs the foreign key, but on the primary key');

        $tags = $description->table('thread_tags');
        self::assertSame(['thread_id', 'threadTag_id'], $tags->primaryKey);
        self::assertSame(['name' => 'thread_id', 'type' => 'integer'], $tags->column('thread_id')->toArray());
        self::assertSame(['FK_EB0D88A83DC29580' => ['threadTag_id', 'threadTag', 'CASCADE'], 'FK_EB0D88A8E2904019' => ['thread_id', 'thread', 'CASCADE']], array_map(fn ($key) => [$key->columns[0], $key->table, $key->onDelete], $tags->foreignKeys));
        self::assertSame(['IDX_EB0D88A83DC29580' => ['threadTag_id'], 'IDX_EB0D88A8E2904019' => ['thread_id']], array_map(fn ($index) => $index->columns, $tags->indexes), 'an index per foreign key: the composite primary key backs neither alone');

        $connexes = $description->table('article_connexes');
        self::assertSame(['article_source', 'article_target'], $connexes->primaryKey);
        self::assertSame(['article', 'article'], [$connexes->foreignKeys['FK_803096ED2CA8B87C']->table, $connexes->foreignKeys['FK_803096ED354DE8F3']->table]);

        $notice = $description->table('notice');
        self::assertSame(['id', 'message', 'locale', 'kind', 'level'], array_keys($notice->columns), 'the root\'s columns, the discriminator, then the subclasses\'');
        self::assertSame(['name' => 'level', 'type' => 'integer', 'nullable' => true], $notice->column('level')->toArray(), 'a subclass\'s column is nullable in the shared table');
        self::assertSame(['name' => 'kind', 'type' => 'string', 'length' => 20], $notice->column('kind')->toArray());
        self::assertSame(['notice_unique_translation' => [['locale'], true]], array_map(fn ($index) => [$index->columns, $index->unique], $notice->indexes), 'the rule\'s unique constraint');

        $user = $description->table('user');
        self::assertSame(['name' => 'balance', 'type' => 'decimal', 'precision' => 10, 'scale' => 2], $user->column('balance')->toArray());
        self::assertSame(['name' => 'score', 'type' => 'integer', 'unsigned' => true, 'default' => 0], $user->column('score')->toArray(), 'the column\'s options');
        self::assertSame(['name' => 'profile_id', 'type' => 'integer', 'nullable' => true], $user->column('profile_id')->toArray(), 'a join column is nullable unless said');
        self::assertSame(['UNIQ_8D93D649CCFA12B8' => [['profile_id'], true], 'UNIQ_8D93D649E7927C74' => [['email'], true], 'idx_balance' => [['balance'], false]], array_map(fn ($index) => [$index->columns, $index->unique], $user->indexes), 'the unique join column\'s index replaces the one its foreign key would add; an index by its fields');
        self::assertSame(['FK_8D93D649CCFA12B8' => ['profile_id', 'profile', null]], array_map(fn ($key) => [$key->columns[0], $key->table, $key->onDelete], $user->foreignKeys));

        self::assertSame(['uniq_tag_label' => [['label'], true]], array_map(fn ($index) => [$index->columns, $index->unique], $description->table('threadTag')->indexes), 'the table the class names, the constraint it names');

        self::assertSame(['id', 'nick', 'order'], array_keys($description->table('profile')->columns), 'a quoted column name (`order`) unquoted, as Doctrine keeps it');
        self::assertSame('order', $description->entity(Profile::class)->field('order')->column);
    }

    public function testTheSameDescriptionFromDeclarationsKeptAsData(): void
    {
        $reader = new AttributeReader();
        $set = DeclarationSet::fromArray(json_decode(json_encode(DeclarationSet::of($reader, self::$classes)->toArray(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR));

        self::assertCount(count(self::$classes), $set->all());
        self::assertSame('threadTag', $set->read(Tag::class)->attribute('Table')->get('name'));
        self::assertSame(self::build()->toArray(), (new Builder($set, self::naming(), [new LocaleRule()]))->build(self::$classes)->toArray(), 'a file of declarations describes the same structure');
    }

    public function testADescriptionReadsBackFromItsPlainForm(): void
    {
        $description = self::build();
        $read = Description::fromJson($description->toJson());

        self::assertSame($description->toArray(), $read->toArray());
        self::assertSame('thread_tags', $read->entity(Thread::class)->relation('tags')->joinTable->name);
        self::assertSame(['thread_id', 'threadTag_id'], $read->table('thread_tags')->primaryKey);
        self::assertSame('CASCADE', $read->table('thread_tags')->foreignKeys['FK_EB0D88A8E2904019']->onDelete);
        self::assertSame('id', $read->entity(Thread::class)->relation('tags')->joinTable->joinColumns[0]->referencedColumn, 'a default left out of the plain form comes back');
    }

    /** @param list<string> $names @return list<string> */
    private static function sorted(array $names): array
    {
        sort($names);

        return $names;
    }
}
