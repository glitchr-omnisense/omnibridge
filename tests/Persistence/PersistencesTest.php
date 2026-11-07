<?php

namespace Omnibridge\Tests\Persistence;

use Omnibridge\Container;
use Omnibridge\Exception\PersistenceException;
use Omnibridge\Persistence\Persistences;
use Omnibridge\Persistence\UnitOfWork;
use PHPUnit\Framework\TestCase;

/** A unit of work of plain PHP, what a persistence's definitions give. */
final class MarkerUnitOfWork implements UnitOfWork
{
    public function __construct(public readonly string $persistence = 'first')
    {
    }

    public function persist(object $entity): void {}
    public function remove(object $entity): void {}
    public function flush(): void {}
    public function refresh(object $entity): void {}
    public function contains(object $entity): bool { return false; }
    public function reference(string $class, mixed $id): object { return new $class(); }
    public function transactional(callable $work): mixed { return $work($this); }
    public function isNew(object $entity): bool { return true; }
    public function identifier(object $entity): mixed { return null; }
    public function className(object $entity): string { return $entity::class; }
    public function changes(object $entity): array { return []; }
    public function recompute(object $entity): void {}
}

/** The services a persistence of the tests declares for a container. */
final class MarkerDefinitions
{
    /** @return list<array<string, mixed>> */
    public static function first(): array
    {
        return [['class' => MarkerUnitOfWork::class, 'arguments' => ['first'], 'contracts' => [UnitOfWork::class]]];
    }

    /** @return list<array<string, mixed>> */
    public static function second(): array
    {
        return [['class' => MarkerUnitOfWork::class, 'id' => 'second.unit_of_work', 'arguments' => ['second'], 'contracts' => [UnitOfWork::class]]];
    }
}

/**
 * The persistence found by what is installed (omnisense/omnibase's plan "omnibase agnostic", step F7d; risk R8): one
 * installed is used, several need the host's option - refused aloud without it -, an option naming one that is not
 * installed or does not exist is refused; Omnibridge\Container adds the chosen one's definitions.
 */
/** A registered persistence's exception of an entity whose row is gone. */
class GoneRow extends \RuntimeException
{
}

final class PersistencesTest extends TestCase
{
    /**
     * The known persistences hidden behind markers that do not exist, so that these cases read the same wherever the
     * suite runs (in an application's suite omnibridge/doctrine is installed beside: its tests cover that).
     */
    protected function setUp(): void
    {
        Persistences::register('doctrine', 'omnibridge/doctrine', 'Omnibridge\Tests\Persistence\Absent\Doctrine');
        Persistences::register('eloquent', 'omnibridge/eloquent', 'Omnibridge\Tests\Persistence\Absent\Eloquent');
    }

    protected function tearDown(): void
    {
        Persistences::unregister('doctrine');
        Persistences::unregister('eloquent');
        Persistences::unregister('first');
        Persistences::unregister('second');
        Persistences::unregister('absent');
    }

    public function testDoctrineAndEloquentAreKnownByTheirPackagesAndMarkers(): void
    {
        self::assertSame('omnibridge/doctrine', Persistences::KNOWN['doctrine']['package']);
        self::assertSame('Omnibridge\Doctrine\EntityManagers', Persistences::KNOWN['doctrine']['marker']);
        self::assertSame('omnibridge/eloquent', Persistences::KNOWN['eloquent']['package']);
        self::assertSame('Omnibridge\Eloquent\EloquentUnitOfWork', Persistences::KNOWN['eloquent']['marker']);
        // Neither is installed here (hidden by setUp() where they are).
        self::assertSame([], Persistences::installed());
        self::assertNull(Persistences::choose(null));
        self::assertNull(Persistences::choose(''));
    }

    public function testAnEntityWhoseRowIsGoneIsSaidByEachPersistencesException(): void
    {
        self::assertSame('Doctrine\ORM\EntityNotFoundException', Persistences::KNOWN['doctrine']['not_found']);
        self::assertSame('Omnibridge\Eloquent\EntityNotFound', Persistences::KNOWN['eloquent']['not_found']);
        self::assertFalse(Persistences::isNotFound(new \RuntimeException('another failure')));
        self::assertFalse(Persistences::isNotFound(new GoneRow()));
        // Where a bridge is installed (an application's suite), its exception is recognised, hidden by setUp() or not.
        foreach (['Doctrine\ORM\EntityNotFoundException' => 'gone', 'Omnibridge\Eloquent\EntityNotFound' => 'gone'] as $class => $message) {
            if (class_exists($class)) {
                self::assertTrue(Persistences::isNotFound(new $class($message)), $class);
            }
        }

        Persistences::register('first', 'acme/first', MarkerUnitOfWork::class, null, GoneRow::class);
        self::assertTrue(Persistences::isNotFound(new GoneRow()));
        self::assertTrue(Persistences::isNotFound(new class() extends GoneRow {}), 'a subclass');
    }

    public function testTheOnlyPersistenceInstalledIsChosen(): void
    {
        Persistences::register('first', 'acme/first', MarkerUnitOfWork::class, MarkerDefinitions::class.'::first');
        Persistences::register('absent', 'acme/absent', 'Acme\Absent\Marker');

        self::assertSame(['first'], Persistences::installed());
        self::assertTrue(Persistences::isInstalled('first'));
        self::assertFalse(Persistences::isInstalled('absent'));
        self::assertSame('first', Persistences::choose(null));
        self::assertSame('first', Persistences::choose('first'));
    }

    public function testSeveralInstalledAndNoneNamedIsRefused(): void
    {
        Persistences::register('first', 'acme/first', MarkerUnitOfWork::class);
        Persistences::register('second', 'acme/second', MarkerDefinitions::class);

        self::assertSame('second', Persistences::choose('second', 'base.persistence'));
        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('acme/first and acme/second are installed: base.persistence in config/packages/base.yaml (or the environment variable OMNIBRIDGE_PERSISTENCE) names the one to run on (first|second), none is picked for you.');
        Persistences::choose(null, 'base.persistence in config/packages/base.yaml');
    }

    public function testANameNoPersistenceHasIsRefused(): void
    {
        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('No persistence is named "propel" (base.persistence): doctrine and eloquent.');
        Persistences::choose('propel', 'base.persistence');
    }

    public function testAPersistenceNotInstalledIsRefused(): void
    {
        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('The persistence "eloquent" (base.persistence) is not installed: composer require omnibridge/eloquent.');
        Persistences::choose('eloquent', 'base.persistence');
    }

    public function testTheEnvironmentNamesItWhereTheOptionDoesNot(): void
    {
        Persistences::register('first', 'acme/first', MarkerUnitOfWork::class);
        Persistences::register('second', 'acme/second', MarkerDefinitions::class);
        putenv('OMNIBRIDGE_PERSISTENCE=second');
        try {
            self::assertSame('second', Persistences::choose(null));
            self::assertSame('first', Persistences::choose('first'), 'the option first');
            Persistences::unregister('second');
            self::assertSame('first', Persistences::choose(null), 'one installed: the environment is not read');
            Persistences::register('second', 'acme/second', MarkerDefinitions::class);
            putenv('OMNIBRIDGE_PERSISTENCE=absent');
            Persistences::register('absent', 'acme/absent', 'Acme\Absent\Marker');
            $this->expectException(PersistenceException::class);
            $this->expectExceptionMessage('The persistence "absent" (OMNIBRIDGE_PERSISTENCE) is not installed: composer require acme/absent.');
            Persistences::choose(null);
        } finally {
            putenv('OMNIBRIDGE_PERSISTENCE');
        }
    }

    public function testDefinitionsAreThePersistenceStaticMethods(): void
    {
        Persistences::register('first', 'acme/first', MarkerUnitOfWork::class, MarkerDefinitions::class.'::first');
        Persistences::register('absent', 'acme/absent', 'Acme\Absent\Marker', MarkerDefinitions::class.'::second');

        self::assertSame(MarkerDefinitions::first(), Persistences::definitions('first'));
        // Not installed: nothing declared, its definitions not read.
        self::assertSame([], Persistences::definitions('absent'));
        self::assertSame([], Persistences::definitions('doctrine'));
        self::assertSame([], Persistences::definitions('unknown'));
    }

    public function testTheContainerAddsTheChosenPersistenceDefinitions(): void
    {
        Persistences::register('first', 'acme/first', MarkerUnitOfWork::class, MarkerDefinitions::class.'::first');

        $container = Container::fromDefinitions([]);
        self::assertSame('first', $container->getParameter('persistence'));
        self::assertSame('first', $container->get(UnitOfWork::class)->persistence);

        Persistences::register('second', 'acme/second', MarkerDefinitions::class, MarkerDefinitions::class.'::second');
        $container = Container::fromDefinitions((static function () { yield ['class' => \ArrayObject::class]; })(), ['persistence' => 'second']);
        self::assertSame('second', $container->getParameter('persistence'));
        self::assertSame('second', $container->get(UnitOfWork::class)->persistence);
        self::assertTrue($container->has(\ArrayObject::class));

        // An object the application gives is kept over the persistence's.
        $given = new MarkerUnitOfWork('given');
        $container = Container::fromDefinitions([], ['persistence' => 'first'], [UnitOfWork::class => $given]);
        self::assertSame($given, $container->get(UnitOfWork::class));
    }

    public function testTheContainerRefusesSeveralPersistencesWithoutItsParameter(): void
    {
        Persistences::register('first', 'acme/first', MarkerUnitOfWork::class, MarkerDefinitions::class.'::first');
        Persistences::register('second', 'acme/second', MarkerDefinitions::class, MarkerDefinitions::class.'::second');

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('the container\'s parameter "persistence" (or the environment variable OMNIBRIDGE_PERSISTENCE) names the one to run on');
        Container::fromDefinitions([]);
    }

    public function testWithoutAPersistenceTheContainerIsAsBefore(): void
    {
        $container = Container::fromDefinitions([['class' => \ArrayObject::class]]);
        self::assertFalse($container->hasParameter('persistence'));
        self::assertFalse($container->has(UnitOfWork::class));
    }
}
