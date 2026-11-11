<?php

namespace Omnibridge\Tests\Schema;

use Omnibridge\Schema\Builder;
use Omnibridge\Schema\CamelCaseNaming;
use Omnibridge\Schema\Database\SchemaCreator;
use Omnibridge\Schema\Database\SqliteSchema;
use Omnibridge\Schema\Description;
use Omnibridge\Schema\ForeignKey;
use Omnibridge\Tests\Fixtures\Schema\Referential\Holding;
use Omnibridge\Tests\Fixtures\Schema\Referential\Owner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A foreign key's referential actions as Doctrine DBAL keeps them in its ForeignKeyConstraint (ON DELETE, ON UPDATE):
 * upper case, RESTRICT and NO ACTION read as no action and left out. The builder, the MySQL and the SQLite readers
 * share it, so the description built without Doctrine is Doctrine's whatever an entity declares.
 */
final class ForeignKeyTest extends TestCase
{
    /** @return iterable<string, array{?string, ?string}> */
    public static function actions(): iterable
    {
        yield 'none' => [null, null];
        yield 'RESTRICT' => ['RESTRICT', null];
        yield 'restrict' => ['restrict', null];
        yield 'Restrict' => ['Restrict', null];
        yield 'NO ACTION' => ['NO ACTION', null];
        yield 'no action' => ['no action', null];
        yield 'No Action' => ['No Action', null];
        yield 'CASCADE' => ['CASCADE', 'CASCADE'];
        yield 'cascade' => ['cascade', 'CASCADE'];
        yield 'SET NULL' => ['SET NULL', 'SET NULL'];
        yield 'set null' => ['set null', 'SET NULL'];
        yield 'SET DEFAULT' => ['SET DEFAULT', 'SET DEFAULT'];
        yield 'set default' => ['set default', 'SET DEFAULT'];
    }

    #[DataProvider('actions')]
    public function testTheActionAsDoctrineDbalKeepsIt(?string $declared, ?string $kept): void
    {
        self::assertSame($kept, ForeignKey::action($declared));
    }

    public function testTheBuilderKeepsEachDeclaredOnDeleteAsDoctrineDoes(): void
    {
        $holding = self::build()->table('referentialHolding');
        $onDelete = [];
        foreach ($holding->foreignKeys as $foreignKey) {
            $onDelete[$foreignKey->columns[0]] = $foreignKey->onDelete;
        }
        ksort($onDelete);

        self::assertSame([
            'cascaded_id' => 'CASCADE',
            'noActionLower_id' => null,
            'noAction_id' => null,
            'plain_id' => null,
            'restrictedLower_id' => null,
            'restricted_id' => null,
            'setNull_id' => 'SET NULL',
        ], $onDelete);
        foreach ($holding->foreignKeys as $foreignKey) {
            self::assertNull($foreignKey->onUpdate, 'no ON UPDATE: Doctrine\'s mapping declares none');
            if (null === $foreignKey->onDelete) {
                self::assertSame(['name', 'columns', 'table', 'referencedColumns'], array_keys($foreignKey->toArray()), $foreignKey->columns[0].': the option left out, as in Doctrine\'s description');
            }
        }
    }

    public function testTheEntityKeepsItsJoinColumnAsDeclared(): void
    {
        $restricted = self::build()->entity(Holding::class)->relation('restricted');

        self::assertSame('RESTRICT', $restricted->joinColumns[0]->onDelete, 'the mapping keeps what is declared, as Doctrine\'s does; only the table\'s key is normalised');
    }

    public function testTheDatabaseCreatedFromItReadsBackAsTheDescription(): void
    {
        $description = self::build();
        $pdo = new \PDO('sqlite::memory:');
        (new SchemaCreator($pdo))->create($description);

        self::assertSame([], SqliteSchema::read($pdo)->differences($description));
    }

    private static function build(): Description
    {
        require_once __DIR__.'/../Fixtures/Schema/Referential/Owner.php';
        require_once __DIR__.'/../Fixtures/Schema/Referential/Holding.php';

        return (new Builder(naming: new CamelCaseNaming(['Omnibridge\\Tests\\Fixtures\\Schema\\'])))->build([Owner::class, Holding::class]);
    }
}
