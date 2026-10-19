<?php

namespace Omnibridge\Tests;

use Omnibridge\Contract\Access;
use Omnibridge\Contract\Environment;
use Omnibridge\Contract\Files;
use Omnibridge\Contract\Locales;
use Omnibridge\Contract\Routes;
use Omnibridge\Contract\Sessions;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Translations;
use Omnibridge\Contract\Urls;
use Omnibridge\Exception\MissingContractException;
use Omnibridge\Runtime;
use Omnibridge\SystemClock;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The registry on its own: a contract asked and not filled is an exception
 * that names it, a container's service is resolved once, a different
 * container forgets what the previous one gave, a service set by hand wins,
 * and a contract is the same whatever name it is asked by.
 */
final class RuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        Runtime::reset();
    }

    protected function tearDown(): void
    {
        Runtime::reset();
    }

    public function testAContractNotFilledIsAnExceptionNamingIt(): void
    {
        self::assertFalse(Runtime::has(Urls::class));
        self::assertNull(Runtime::find(Urls::class));

        $this->expectException(MissingContractException::class);
        $this->expectExceptionMessage(Urls::class);
        Runtime::urls();
    }

    public function testEveryTypedAccessorThrowsWhenEmptyButTheClock(): void
    {
        foreach (['urls', 'translations', 'locales', 'access', 'sessions', 'tokens', 'files', 'routes', 'environment', 'events'] as $accessor) {
            try {
                Runtime::$accessor();
                self::fail($accessor.'() answered with nothing filled');
            } catch (MissingContractException) {
                self::addToAssertionCount(1);
            }
        }

        self::assertInstanceOf(SystemClock::class, Runtime::clock());
        self::assertEqualsWithDelta(time(), Runtime::clock()->now()->getTimestamp(), 2);
    }

    public function testAServiceSetByHandIsAnsweredAndMustImplementTheContract(): void
    {
        $urls = self::urls('/a');
        Runtime::set(Urls::class, $urls);

        self::assertTrue(Runtime::has(Urls::class));
        self::assertSame($urls, Runtime::urls());
        self::assertSame('/a/blog', Runtime::urls()->path('blog'));

        Runtime::set(Urls::class, null);
        self::assertFalse(Runtime::has(Urls::class));

        $this->expectException(MissingContractException::class);
        Runtime::set(Urls::class, new \stdClass());
    }

    public function testTheContainerIsAskedOnceByTheContractsName(): void
    {
        $container = self::container([Urls::class => self::urls('/c')]);
        Runtime::setContainer($container);

        self::assertTrue(Runtime::has(Urls::class));
        self::assertFalse(Runtime::has(Access::class));
        self::assertSame(Runtime::urls(), Runtime::urls());
        self::assertSame(1, $container->gets);
    }

    public function testAServiceOfTheWrongTypeInTheContainerIsRefused(): void
    {
        Runtime::setContainer(self::container([Urls::class => new \stdClass()]));

        $this->expectException(MissingContractException::class);
        $this->expectExceptionMessage('stdClass');
        Runtime::urls();
    }

    public function testADifferentContainerForgetsWhatThePreviousGaveAndKeepsWhatWasSet(): void
    {
        $access = $this->createMock(Access::class);
        Runtime::set(Access::class, $access);
        Runtime::setContainer(self::container([Urls::class => self::urls('/old'), Access::class => $this->createMock(Access::class)]));
        self::assertSame('/old/x', Runtime::urls()->path('x'));

        Runtime::setContainer(Runtime::getContainer());
        self::assertSame('/old/x', Runtime::urls()->path('x'), 'the same container: nothing forgotten');

        Runtime::setContainer(self::container([Urls::class => self::urls('/new')]));
        self::assertSame('/new/x', Runtime::urls()->path('x'));
        self::assertSame($access, Runtime::access(), 'set by hand: ahead of any container');
    }

    public function testTheClockIsTheContainersWhenItHasOne(): void
    {
        $frozen = new class implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-10-18 02:00:00', new \DateTimeZone('UTC'));
            }
        };
        self::assertInstanceOf(SystemClock::class, Runtime::clock());

        Runtime::setContainer(self::container([ClockInterface::class => $frozen]));
        self::assertSame($frozen, Runtime::clock(), 'the machine\'s clock was the empty registry\'s, not kept over a container\'s');
        self::assertSame('2026-10-18T02:00:00+00:00', Runtime::clock()->now()->format(\DATE_ATOM));
    }

    public function testEachAccessorAnswersItsContract(): void
    {
        $services = [
            Urls::class => self::urls(''),
            Translations::class => $this->createMock(Translations::class),
            Locales::class => $this->createMock(Locales::class),
            Access::class => $this->createMock(Access::class),
            Sessions::class => $this->createMock(Sessions::class),
            Tokens::class => $this->createMock(Tokens::class),
            Files::class => $this->createMock(Files::class),
            Routes::class => $this->createMock(Routes::class),
            Environment::class => $this->createMock(Environment::class),
            EventDispatcherInterface::class => $this->createMock(EventDispatcherInterface::class),
        ];
        Runtime::setContainer(self::container($services));

        foreach (['urls' => Urls::class, 'translations' => Translations::class, 'locales' => Locales::class, 'access' => Access::class, 'sessions' => Sessions::class, 'tokens' => Tokens::class, 'files' => Files::class, 'routes' => Routes::class, 'environment' => Environment::class, 'events' => EventDispatcherInterface::class] as $accessor => $contract) {
            self::assertSame($services[$contract], Runtime::$accessor(), $accessor.'()');
        }
    }

    /**
     * A former name kept by class_alias is the same contract: set or bound
     * under either name, it is found under both, one service, resolved once.
     */
    public function testAContractIsTheSameUnderAFormerName(): void
    {
        class_exists(Fixtures\FormerNames::class);
        $former = Fixtures\FormerNames::URLS;
        self::assertSame(Urls::class, (new \ReflectionClass($former))->getName(), 'an alias, not a subclass');

        $urls = self::urls('/set');
        Runtime::set($former, $urls);
        self::assertSame($urls, Runtime::urls(), 'set by its former name, found by its own');
        self::assertSame($urls, Runtime::get($former));
        Runtime::set(Urls::class, null);
        self::assertFalse(Runtime::has($former), 'taken back by its own name, gone under the former');

        $byFormer = self::container([$former => self::urls('/former')]);
        Runtime::setContainer($byFormer);
        self::assertTrue(Runtime::has($former));
        self::assertSame('/former/x', Runtime::get($former)->path('x'), 'a container that binds only the former name, asked by it');
        self::assertSame(Runtime::get($former), Runtime::urls(), 'then known by its own name too: one service');
        self::assertSame(1, $byFormer->gets);

        $byBoth = self::container([Urls::class => self::urls('/own'), $former => self::urls('/former')]);
        Runtime::setContainer($byBoth);
        self::assertSame('/own/x', Runtime::get($former)->path('x'), 'the own name first');
        self::assertSame(Runtime::get($former), Runtime::urls());
        self::assertSame(1, $byBoth->gets);
    }

    public function testResetForgetsEverything(): void
    {
        Runtime::set(Urls::class, self::urls(''));
        Runtime::setContainer(self::container([]));
        Runtime::reset();

        self::assertNull(Runtime::getContainer());
        self::assertFalse(Runtime::has(Urls::class));
    }

    /** A class of an application family over the same registry shares its state. */
    public function testAFamilysOwnRegistryIsTheSameRegistry(): void
    {
        $urls = self::urls('/family');
        Fixtures\FamilyRuntime::set(Urls::class, $urls);

        self::assertSame($urls, Runtime::urls());
        self::assertSame($urls, Fixtures\FamilyRuntime::urls());
        self::assertSame('/family/x', Fixtures\FamilyRuntime::link('x'));
    }

    private static function urls(string $prefix): Urls
    {
        return new class($prefix) implements Urls {
            public function __construct(private string $prefix)
            {
            }

            public function path(string $name, array $parameters = []): string
            {
                return $this->prefix.'/'.$name;
            }

            public function url(string $name, array $parameters = []): string
            {
                return 'https://example.test'.$this->path($name, $parameters);
            }

            public function asset(string $path): string
            {
                return '/'.$path;
            }
        };
    }

    /** @param array<string, object> $services */
    private static function container(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            public int $gets = 0;

            /** @param array<string, object> $services */
            public function __construct(private array $services)
            {
            }

            public function get(string $id): object
            {
                ++$this->gets;

                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }
}
