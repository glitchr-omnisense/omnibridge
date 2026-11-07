<?php

namespace Omnibridge\Tests\Persistence;

use Omnibridge\Persistence\Lifecycle\EntityEvent;
use Omnibridge\Persistence\Lifecycle\FlushEvent;
use Omnibridge\Persistence\Lifecycle\Listeners;
use Omnibridge\Persistence\Lifecycle\OnFlush;
use Omnibridge\Persistence\Lifecycle\PostFlush;
use Omnibridge\Persistence\Lifecycle\PostLoad;
use Omnibridge\Persistence\Lifecycle\PostPersist;
use Omnibridge\Persistence\Lifecycle\PostRemove;
use Omnibridge\Persistence\Lifecycle\PostUpdate;
use Omnibridge\Persistence\Lifecycle\PreFlush;
use Omnibridge\Persistence\Lifecycle\PrePersist;
use Omnibridge\Persistence\Lifecycle\PreRemove;
use Omnibridge\Persistence\Lifecycle\PreUpdate;
use Omnibridge\Persistence\UnitOfWork;
use PHPUnit\Framework\TestCase;

/**
 * The persistence's moments, as a backend dispatches them: an entity's (with its unit of work, and
 * recompute() asking it again) and a flush's; PreUpdate's changes, which a listener may change for a
 * changed field only.
 */
final class LifecycleTest extends TestCase
{
    public function testTheEntityEventsCarryTheEntityAndRecomputeThroughTheUnitOfWork(): void
    {
        $entity = new \stdClass();
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects(self::exactly(7))->method('recompute')->with($entity);

        foreach ([PrePersist::class, PostPersist::class, PreRemove::class, PostRemove::class, PostUpdate::class, PostLoad::class] as $class) {
            $event = new $class($entity, $unitOfWork);
            self::assertInstanceOf(EntityEvent::class, $event);
            self::assertSame($entity, $event->entity);
            $event->recompute();
        }
        (new PreUpdate($entity, $unitOfWork, []))->recompute();
    }

    public function testTheFlushEventsCarryTheUnitOfWork(): void
    {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        foreach ([PreFlush::class, OnFlush::class, PostFlush::class] as $class) {
            $event = new $class($unitOfWork);
            self::assertInstanceOf(FlushEvent::class, $event);
            self::assertSame($unitOfWork, $event->unitOfWork);
        }
    }

    public function testAChangedFieldGetsANewValueBeforeTheUpdate(): void
    {
        $event = new PreUpdate(new \stdClass(), $this->createMock(UnitOfWork::class), ['slug' => ['old', 'new']]);
        self::assertTrue($event->hasChanged('slug'));
        self::assertFalse($event->hasChanged('title'));

        $event->setNewValue('slug', 'newer');
        self::assertSame(['slug' => ['old', 'newer']], $event->changes());

        $this->expectExceptionMessage('did not change');
        $event->setNewValue('title', 'A title');
    }

    public function testTheListenersAPackageDeclaresAreMadeAtTheFirstEventThenCalledInTheirOrder(): void
    {
        self::assertSame('omnibridge.lifecycle_listener', Listeners::TAG);
        $made = 0;
        $calls = [];
        $chain = Listeners::chain(static function () use (&$made, &$calls): iterable {
            ++$made;
            yield static function (object $event) use (&$calls): void { $calls[] = ['first', $event::class]; };
            yield static function (object $event) use (&$calls): void { $calls[] = ['second', $event::class]; };
        });
        self::assertSame(0, $made, 'nothing made before an event');

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $chain(new PostPersist(new \stdClass(), $unitOfWork));
        $chain(new PostFlush($unitOfWork));

        self::assertSame(1, $made);
        self::assertSame([['first', PostPersist::class], ['second', PostPersist::class], ['first', PostFlush::class], ['second', PostFlush::class]], $calls);
    }

    public function testAListenerThatIsNoCallableIsRefusedAloud(): void
    {
        $chain = Listeners::chain([new \stdClass()]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('omnibridge.lifecycle_listener');
        $chain(new PostFlush($this->createStub(UnitOfWork::class)));
    }
}
