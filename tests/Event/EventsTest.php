<?php

namespace Omnibridge\Tests\Event;

use Omnibridge\Event\Events;
use Omnibridge\Event\NamedEvent;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

final class EventsTest extends TestCase
{
    public function testADispatcherListeningByNameIsGivenTheName(): void
    {
        $dispatcher = new class {
            public array $seen = [];

            public function dispatch(object $event, ?string $name = null): object
            {
                $this->seen[] = $name;

                return $event;
            }
        };
        $psr = new class($dispatcher) implements EventDispatcherInterface {
            public function __construct(public object $inner)
            {
            }

            public function dispatch(object $event, ?string $name = null): object
            {
                return $this->inner->dispatch($event, $name);
            }
        };

        Events::dispatch($psr, new \stdClass(), 'user.register');
        Events::dispatch($psr, self::named('thread.publish'));
        Events::dispatch($psr, new \stdClass());

        self::assertSame(['user.register', 'thread.publish', null], $dispatcher->seen);
    }

    public function testAPsrDispatcherIsGivenTheEventAlone(): void
    {
        $psr = new class implements EventDispatcherInterface {
            public array $seen = [];

            public function dispatch(object $event): object
            {
                $this->seen[] = $event instanceof NamedEvent ? $event->eventName() : $event::class;

                return $event;
            }
        };

        $event = self::named('user.register');
        self::assertSame($event, Events::dispatch($psr, $event, 'user.register'));
        self::assertSame(['user.register'], $psr->seen);
    }

    private static function named(string $name): NamedEvent
    {
        return new class($name) implements NamedEvent {
            public function __construct(private readonly string $name)
            {
            }

            public function eventName(): string
            {
                return $this->name;
            }
        };
    }
}
