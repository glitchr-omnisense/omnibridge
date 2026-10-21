<?php

namespace Omnibridge\Tests\Schema;

use Omnibridge\Schema\Declaration\Attribute;
use Omnibridge\Schema\Declaration\AttributeReader;
use Omnibridge\Schema\Declaration\Vocabulary;
use Omnibridge\Tests\Fixtures\Schema\Entity\Article;
use Omnibridge\Tests\Fixtures\Schema\Entity\Notice;
use Omnibridge\Tests\Fixtures\Schema\Entity\Tag;
use Omnibridge\Tests\Fixtures\Schema\Entity\Thread;
use Omnibridge\Tests\Fixtures\Schema\Entity\Timestamps;
use Omnibridge\Tests\Fixtures\Schema\State;
use PHPUnit\Framework\TestCase;

/** The mapping attributes read as data, their classes absent: doctrine/orm is not installed here. */
final class AttributeReaderTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require __DIR__.'/../Fixtures/Schema/load.php';
    }

    public function testTheVocabularyIsNotInstalled(): void
    {
        // An application that runs this suite from its vendor/ installs the ORM:
        // the absence is measured on this package's own vendor only (as BareTest).
        if ('omniscient/omnibridge' !== \Composer\InstalledVersions::getRootPackage()['name']) {
            self::markTestSkipped('Measured on omniscient/omnibridge\'s own vendor, not on '.\Composer\InstalledVersions::getRootPackage()['name'].'\'s.');
        }

        self::assertFalse(class_exists(Vocabulary::NAMESPACE.'Column'), 'the declarations are read without the ORM');
    }

    public function testAClassDeclarationItsKindAndItsAttributes(): void
    {
        $reader = new AttributeReader();
        $thread = $reader->read(Thread::class);

        self::assertSame('Entity', $thread->kind());
        self::assertSame('MappedSuperclass', $reader->read(Timestamps::class)->kind());
        self::assertNull($reader->read(State::class), 'a class that is no entity');
        self::assertNull($reader->read(\stdClass::class));

        self::assertSame(['Entity', 'InheritanceType', 'DiscriminatorColumn'], array_map(fn (Attribute $attribute) => $attribute->name, $thread->attributes), 'in the order written');
        self::assertSame('JOINED', $thread->attribute('InheritanceType')->get('value'), 'a positional argument named after its parameter');
        self::assertSame(['name' => 'class', 'type' => 'string'], $thread->attribute('DiscriminatorColumn')->arguments, 'as written');
        self::assertSame(255, $thread->attribute('DiscriminatorColumn')->get('length') ?? 255);
        self::assertNull($thread->attribute('DiscriminatorColumn')->get('length'), 'the vocabulary\'s default of an argument left out');
        self::assertFalse($thread->attribute('DiscriminatorColumn')->has('length'));
        self::assertNull($thread->attribute('Table'));
        self::assertSame('threadTag', $reader->read(Tag::class)->attribute('Table')->get('name'));
        self::assertSame(['uniq_tag_label'], array_map(fn (Attribute $attribute) => $attribute->get('name'), $reader->read(Tag::class)->attributes('UniqueConstraint')));
        self::assertSame(['notice' => Notice::class, 'alert' => 'Omnibridge\\Tests\\Fixtures\\Schema\\Entity\\Alert'], $reader->read(Notice::class)->attribute('DiscriminatorMap')->get('value'));
    }

    public function testThePropertiesWithTheClassThatDeclaresEach(): void
    {
        $reader = new AttributeReader();
        $article = $reader->read(Article::class);

        self::assertSame(['title', 'author', 'connexes', 'id', 'slug', 'state', 'tags', 'createdAt'], array_map(fn ($property) => $property->name, $article->properties), 'reflection\'s order: its own, then the inherited ones');
        self::assertSame([Article::class, Thread::class, Timestamps::class], [$article->property('title')->class, $article->property('slug')->class, $article->property('createdAt')->class]);
        self::assertSame(['Id', 'GeneratedValue', 'Column'], array_map(fn (Attribute $attribute) => $attribute->name, $article->property('id')->attributes));
        self::assertSame(['Column'], array_map(fn (Attribute $attribute) => $attribute->name, $article->property('createdAt')->attributes));
        self::assertSame([], $article->property('createdAt')->attribute('Column')->arguments);
        self::assertSame(['targetEntity' => Tag::class, 'inversedBy' => 'threads'], $article->property('tags')->attribute('ManyToMany')->arguments);
        self::assertSame('LAZY', $article->property('tags')->attribute('ManyToMany')->get('fetch'), 'the vocabulary\'s default');
        self::assertSame(['nullable' => false, 'onDelete' => 'SET NULL'], $article->property('author')->attribute('JoinColumn')->arguments);
        self::assertSame(['ManyToOne'], array_map(fn (Attribute $attribute) => $attribute->name, $article->property('author')->attributes('ManyToOne')));
        self::assertSame([], $article->methods, 'no method carries a lifecycle attribute');
    }

    public function testTheVocabularyAnswersForEveryAttributeOfTheMapping(): void
    {
        self::assertSame('Column', Vocabulary::nameOf('Doctrine\\ORM\\Mapping\\Column'));
        self::assertNull(Vocabulary::nameOf('Doctrine\\ORM\\Mapping\\Nothing'));
        self::assertNull(Vocabulary::nameOf('App\\Attribute\\Column'));
        self::assertSame(['name', 'type', 'length', 'precision', 'scale', 'unique', 'nullable', 'insertable', 'updatable', 'enumType', 'options', 'columnDefinition', 'generated', 'index'], Vocabulary::parameters('Column'), 'in the constructor\'s order');
        self::assertSame(['strategy' => 'AUTO'], Vocabulary::defaults('GeneratedValue'));
        self::assertArrayNotHasKey('targetEntity', Vocabulary::defaults('ManyToMany'), 'a required parameter has no default');
        self::assertNull(Vocabulary::defaultOf('ManyToMany', 'targetEntity'));
        self::assertTrue(Vocabulary::isRepeatable('JoinColumn'));
        self::assertFalse(Vocabulary::isRepeatable('Column'));
        self::assertSame('prePersist', Vocabulary::CALLBACKS['PrePersist']);
    }

    public function testADeclarationReadsBackFromItsPlainForm(): void
    {
        $declaration = (new AttributeReader())->read(Article::class);
        $read = \Omnibridge\Schema\Declaration\ClassDeclaration::fromArray(json_decode(json_encode($declaration->toArray(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR));

        self::assertSame($declaration->toArray(), $read->toArray());
        self::assertSame('SET NULL', $read->property('author')->attribute('JoinColumn')->get('onDelete'));
    }
}
