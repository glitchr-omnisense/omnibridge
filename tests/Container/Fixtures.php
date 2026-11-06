<?php

namespace Omnibridge\Tests\Container;

interface Clock
{
    public function now(): string;
}

final class FixedClock implements Clock
{
    public function now(): string
    {
        return '2026-11-02';
    }
}

final class Greeter
{
    public function __construct(
        public readonly Clock $clock,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%app.name%')]
        public readonly string $name,
        public readonly string $greeting = 'Hello',
        public readonly ?Missing $missing = null,
    ) {
    }
}

final class Mailer
{
    public function __construct(public readonly Greeter $greeter, public readonly string $from, public readonly array $options = [])
    {
    }
}

interface Missing
{
}

final class Unwirable
{
    public function __construct(public readonly string $required)
    {
    }
}

final class Chicken
{
    public function __construct(public readonly Egg $egg)
    {
    }
}

final class Egg
{
    public function __construct(public readonly Chicken $chicken)
    {
    }
}

final class Clocked
{
    public function __construct(public readonly NotAnswered|Clock $clock)
    {
    }
}

interface NotAnswered
{
}

/** A class built through a list: its parts by position, a call, a decorated one, the services of a tag. */
final class Recorder
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(public readonly string $name, public readonly string $place = 'here', public readonly ?Clock $clock = null)
    {
    }

    public function record(string $what): void
    {
        $this->calls[] = $what;
    }

    public static function create(string $name): self
    {
        return new self($name.' (made)');
    }
}

/** Wraps another recorder (a decoration). */
final class LoudRecorder
{
    public function __construct(public readonly Recorder $inner)
    {
    }
}

/** Takes the services of a tag. */
final class Recorders
{
    /** @param iterable<Recorder> $recorders */
    public function __construct(public readonly iterable $recorders, public readonly ?\Closure $later = null)
    {
    }
}
