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
