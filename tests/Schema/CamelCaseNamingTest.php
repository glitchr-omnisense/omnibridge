<?php

namespace Omnibridge\Tests\Schema;

use Omnibridge\Schema\CamelCaseNaming;
use Omnibridge\Tests\Fixtures\Schema\Entity\Tag;
use PHPUnit\Framework\TestCase;

/** omnibase's names, on their own (omnibridge/doctrine's NamingStrategy, which answers with this class, checks them against every table of lesringostars). */
final class CamelCaseNamingTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require __DIR__.'/../Fixtures/Schema/load.php';
    }

    public function testTheTablesAndColumns(): void
    {
        $naming = new CamelCaseNaming();

        self::assertSame('threadTag', $naming->classToTableName('App\Entity\Thread\Tag'));
        self::assertSame('userProfile', $naming->classToTableName('App\Entity\User\Profile'));
        self::assertSame('threadIntl', $naming->classToTableName('Base\Entity\ThreadIntl'));
        self::assertSame('articleCommentReply', $naming->classToTableName('App\Entity\Article\CommentReply'));
        self::assertSame('thread_tags', $naming->joinTableName('Base\Entity\Thread', 'App\Entity\Thread\Tag', 'tags'));
        self::assertSame('thread_threadTag', $naming->joinTableName('Base\Entity\Thread', 'App\Entity\Thread\Tag', ''), 'without a property: the target\'s table');
        self::assertSame('publishedAt', $naming->propertyToColumnName('publishedAt', 'Base\Entity\Thread'));
        self::assertSame('translatable_id', $naming->joinColumnName('translatable', 'Base\Entity\ThreadIntl'));
        self::assertSame('threadTag_id', $naming->joinKeyColumnName('App\Entity\Thread\Tag', null));
        self::assertSame('thread_source', $naming->joinKeyColumnName('Base\Entity\Thread', 'source'));
        self::assertSame('id', $naming->referenceColumnName());
        self::assertSame('address_street', $naming->embeddedFieldToColumnName('address', 'street', 'App\Entity\User', 'App\Entity\Address'));
        self::assertSame('', $naming->classToTableName(''));
    }

    public function testAClassThatNamesItsTableAndThePrefixesGiven(): void
    {
        self::assertSame('threadTag', (new CamelCaseNaming())->classToTableName(Tag::class), '#[Table(name: ...)] read without the ORM');
        self::assertSame('omnibridgeTestsFixturesSchemaThread', (new CamelCaseNaming())->classToTableName('Omnibridge\Tests\Fixtures\Schema\Entity\Thread'));
        self::assertSame('thread', (new CamelCaseNaming(['Omnibridge\\Tests\\Fixtures\\Schema\\']))->classToTableName('Omnibridge\Tests\Fixtures\Schema\Entity\Thread'));
    }

    public function testANameGeneratedAsDbalGeneratesIt(): void
    {
        $naming = new CamelCaseNaming();

        self::assertSame(strtoupper('idx_'.dechex(crc32('thread')).dechex(crc32('slug'))), $naming->generatedName('thread', ['slug'], 'idx'));
        self::assertSame('UNIQ_31204C83989D9B62', $naming->generatedName('thread', ['slug'], 'uniq'));
        self::assertSame('FK_EB0D88A8E2904019', $naming->generatedName('thread_tags', ['thread_id'], 'fk'));
        self::assertSame(64, strlen($naming->generatedName('a', array_fill(0, 12, 'column'), 'idx')), 'at most 64 characters');
    }

    public function testATableNameTooLongIsRefused(): void
    {
        $this->expectException(\LengthException::class);
        (new CamelCaseNaming())->classToTableName('App\Entity\A'.str_repeat('b', 69));
    }
}
