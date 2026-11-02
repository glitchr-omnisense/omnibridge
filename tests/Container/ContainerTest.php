<?php

namespace Omnibridge\Tests\Container;

use Omnibridge\Container;
use Omnibridge\Exception\ContainerException;
use Omnibridge\Exception\ServiceNotFoundException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

require_once __DIR__.'/Fixtures.php';

final class ContainerTest extends TestCase
{
    public function testAServiceIsAutowiredFromItsConstructor(): void
    {
        $container = (new Container(['app.name' => 'Les Ringo Stars']))->alias(Clock::class, FixedClock::class);

        $greeter = $container->get(Greeter::class);
        self::assertInstanceOf(FixedClock::class, $greeter->clock);
        self::assertSame('Les Ringo Stars', $greeter->name, 'Symfony\'s #[Autowire] read by its name, never loaded');
        self::assertSame('Hello', $greeter->greeting, 'the default value');
        self::assertNull($greeter->missing, 'an optional service nobody answers');
        self::assertSame($greeter, $container->get(Greeter::class), 'shared');
        self::assertFalse(class_exists(Container::AUTOWIRE_ATTRIBUTE, false));
    }

    public function testDefinitionsAsDataGiveArgumentsByName(): void
    {
        $container = Container::fromDefinitions([
            ['class' => FixedClock::class, 'contracts' => [Clock::class, 'clock']],
            ['class' => Mailer::class, 'id' => 'mailer', 'arguments' => ['$from' => 'noreply@%app.domain%', 'options' => ['%app.name%', '@@literal', '%%']]],
            ['class' => 'Omnibridge\Tests\Container\NotInstalled', 'contracts' => ['nothing']],
        ], ['app.name' => 'Ringo', 'app.domain' => 'lesringostars.test']);

        $mailer = $container->get('mailer');
        self::assertInstanceOf(Mailer::class, $mailer);
        self::assertSame('noreply@lesringostars.test', $mailer->from);
        self::assertSame(['Ringo', '@literal', '%'], $mailer->options);
        self::assertSame($container->get('clock'), $mailer->greeter->clock);
        self::assertFalse($container->has('nothing'), 'a class not installed is left out, with its contracts');
        self::assertSame($container, $container->get(ContainerInterface::class));
    }

    public function testFactoriesAndObjectsGiven(): void
    {
        $container = new Container(['app.name' => 'x']);
        $container->factory(Clock::class, static fn (Container $c): Clock => new FixedClock());
        $calls = 0;
        $container->factory('counted', static function () use (&$calls): object {
            ++$calls;

            return new \stdClass();
        });
        $container->set('given', $object = new \stdClass());

        self::assertInstanceOf(FixedClock::class, $container->get(Greeter::class)->clock);
        $container->get('counted');
        $container->get('counted');
        self::assertSame(1, $calls);
        self::assertSame($object, $container->get('given'));
    }

    public function testWhatCannotBeBuiltIsRefusedAloud(): void
    {
        $container = new Container();
        self::assertFalse($container->has(Clock::class));
        try {
            $container->get(Clock::class);
            self::fail('an interface nobody answers');
        } catch (NotFoundExceptionInterface $e) {
            self::assertInstanceOf(ServiceNotFoundException::class, $e);
        }

        try {
            $container->get(Unwirable::class);
            self::fail('a scalar without a value');
        } catch (ContainerException $e) {
            self::assertStringContainsString('$required', $e->getMessage());
        }

        try {
            $container->get(Chicken::class);
            self::fail('a cycle');
        } catch (ContainerException $e) {
            self::assertStringContainsString('A cycle', $e->getMessage());
        }

        $this->expectException(ServiceNotFoundException::class);
        $container->getParameter('nope');
    }

    public function testAUnionTakesTheTypeSomethingAnswersWhereverItIs(): void
    {
        $container = (new Container())->alias(Clock::class, FixedClock::class);
        self::assertInstanceOf(FixedClock::class, $container->get(Clocked::class)->clock);
    }

    public function testEnvironmentParameters(): void
    {
        putenv('OMNIBRIDGE_CONTAINER_TEST=yes');
        try {
            self::assertSame('yes', (new Container())->getParameter('env(OMNIBRIDGE_CONTAINER_TEST)'));
        } finally {
            putenv('OMNIBRIDGE_CONTAINER_TEST');
        }
    }
}
