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
        $attributeLoaded = class_exists(Container::AUTOWIRE_ATTRIBUTE, false);
        $container = (new Container(['app.name' => 'Les Ringo Stars']))->alias(Clock::class, FixedClock::class);

        $greeter = $container->get(Greeter::class);
        self::assertInstanceOf(FixedClock::class, $greeter->clock);
        self::assertSame('Les Ringo Stars', $greeter->name, 'Symfony\'s #[Autowire] read by its name, never loaded');
        self::assertSame('Hello', $greeter->greeting, 'the default value');
        self::assertNull($greeter->missing, 'an optional service nobody answers');
        self::assertSame($greeter, $container->get(Greeter::class), 'shared');
        self::assertSame($attributeLoaded, class_exists(Container::AUTOWIRE_ATTRIBUTE, false), 'the attribute\'s class is not loaded by the container (an application\'s suite may have loaded it before)');
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

    /**
     * The declaration's format as omnisense/omnibase writes its services (Base\Services, step F5e slice 4): a parent and
     * its abstract definition, arguments by position (and Symfony's index_N over a parent's), bindings, calls (one
     * dropped where the service it names may be ignored), tags and a tagged iterator, a service closure, a decoration
     * (and one of a service that is not there, dropped), a factory, requirements and an environment, contracts by name
     * with their options; what only Symfony reads (`symfony`, `public`, `autowire`...) is not read here.
     */
    public function testTheDeclarationsFormat(): void
    {
        $container = Container::fromDefinitions([
            ['class' => Recorder::class, 'id' => 'recorder.base', 'abstract' => true, 'arguments' => ['first', 'there'], 'calls' => [['record', ['inherited']]]],
            ['parent' => 'recorder.base', 'id' => 'recorder.child', 'arguments' => ['index_0' => 'child'], 'tags' => ['recorders', ['name' => 'other', 'priority' => 3]],
                'calls' => [['record', ['own']], ['record', ['@??nowhere']]], 'public' => true, 'autowire' => false, 'symfony' => ['arguments' => ['@request_stack']]],
            ['class' => Recorder::class, 'id' => 'recorder.bound', 'bind' => ['$name' => 'bound', '$place' => '%app.place%'], 'tags' => ['recorders'],
                'contracts' => ['recorder.alias' => ['public' => true], 'recorder.second' => ['target' => 'recorder.alias']]],
            ['class' => Recorder::class, 'id' => 'recorder.made', 'factory' => [Recorder::class, 'create'], 'arguments' => ['$name' => 'factory']],
            ['class' => LoudRecorder::class, 'decorates' => ['id' => 'recorder.made'], 'arguments' => ['@.inner']],
            ['class' => LoudRecorder::class, 'id' => 'loud.nothing', 'decorates' => ['id' => 'nowhere', 'on_invalid' => 'ignore'], 'arguments' => ['@.inner']],
            ['class' => Recorders::class, 'arguments' => [['!tagged_iterator' => 'recorders'], ['!service_closure' => '@?nowhere']]],
            ['class' => Recorder::class, 'id' => 'recorder.demo', 'environment' => 'demo', 'arguments' => ['demo']],
            ['class' => Recorder::class, 'id' => 'recorder.missing', 'requires' => ['Omnibridge\Tests\Container\NotInstalled'], 'arguments' => ['x']],
        ], ['app.place' => 'Strasbourg', 'kernel.environment' => 'prod']);

        $child = $container->get('recorder.child');
        self::assertSame(['child', 'there'], [$child->name, $child->place], 'the parent\'s arguments, the child\'s index_0 over them');
        self::assertSame(['inherited', 'own'], $child->calls, 'the parent\'s calls then its own, the one naming a service that may be ignored dropped');
        self::assertFalse($container->has('recorder.base'), 'an abstract definition is a parent only');

        $bound = $container->get('recorder.bound');
        self::assertSame(['bound', 'Strasbourg'], [$bound->name, $bound->place]);
        self::assertSame($bound, $container->get('recorder.alias'));
        self::assertSame($bound, $container->get('recorder.second'));

        $loud = $container->get('recorder.made');
        self::assertInstanceOf(LoudRecorder::class, $loud, 'the decorated id answers the decorator');
        self::assertSame('factory (made)', $loud->inner->name, 'given the former service, made by its factory');
        self::assertFalse($container->has('loud.nothing'), 'a decoration of a service that is not there, dropped');

        $recorders = $container->get(Recorders::class);
        self::assertSame([$child, $bound], $recorders->recorders, 'the services of a tag, in their order');
        self::assertNull(($recorders->later)(), 'a service closure');
        self::assertSame(['recorder.child'], array_values(array_intersect($container->ids(), ['recorder.child'])));
        self::assertFalse($container->has('recorder.demo'), 'another environment\'s');
        self::assertFalse($container->has('recorder.missing'), 'what it requires is not installed');
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
    /**
     * The registries of what is discovered (Container\Discovery, omnisense/omnibase's step F5e slice 4c): an autoconfigured
     * definition gets the tags of the interfaces its class implements, after its own (a parent's autoconfiguration
     * inherited, one whose requirement is not installed ignored); a collection hands the tagged services - and those a
     * class names, by instanceof - to its collecting service in their order, a service that is none here dropped; a
     * fallback binds the first candidate defined, else its default; a service given is never replaced.
     */
    public function testWhatIsDiscovered(): void
    {
        $discovery = [
            'autoconfigure' => [
                ['instanceof' => Plugin::class, 'tags' => ['plugins', ['name' => 'listener', 'event' => 'saved']]],
                ['instanceof' => Plugin::class, 'tags' => ['never'], 'requires' => ['Omnibridge\Tests\Container\NotInstalled']],
            ],
            'collect' => [
                ['service' => 'collector', 'method' => 'add', 'tag' => 'plugins'],
                ['service' => 'by.class', 'method' => 'add', 'instanceof' => Plugin::class],
                ['service' => 'nowhere', 'method' => 'add', 'tag' => 'plugins'],
            ],
            'fallbacks' => [
                Linker::class => ['candidates' => ['linker.admin'], 'default' => NullLinker::class],
                'given' => ['default' => NullLinker::class],
            ],
        ];
        $definitions = [
            ['class' => Collector::class, 'id' => 'collector'],
            ['class' => Collector::class, 'id' => 'by.class'],
            ['class' => FirstPlugin::class, 'autoconfigure' => true, 'tags' => ['own']],
            ['class' => 'Omnibridge\Tests\Container\NotInstalled', 'id' => 'missing', 'tags' => ['plugins']],
            ['class' => SecondPlugin::class, 'id' => 'second.base', 'abstract' => true, 'autoconfigure' => true],
            ['parent' => 'second.base', 'id' => SecondPlugin::class],
            ['class' => FirstPlugin::class, 'id' => 'first.plain'],
        ];
        $resolved = Container\Discovery::autoconfigure(Container\Declarations::resolve($definitions), $discovery['autoconfigure']);
        self::assertSame(['own', 'plugins', ['name' => 'listener', 'event' => 'saved']], $resolved[FirstPlugin::class]['tags']);
        self::assertSame(['plugins', ['name' => 'listener', 'event' => 'saved']], $resolved[SecondPlugin::class]['tags'], 'the parent\'s autoconfiguration');
        self::assertSame([], $resolved['first.plain']['tags'], 'not autoconfigured');
        self::assertSame([FirstPlugin::class => [['event' => 'saved']], SecondPlugin::class => [['event' => 'saved']]], Container\Discovery::attributes($resolved, 'listener'));

        $container = Container::fromDefinitions($definitions, [], ['given' => new FirstPlugin()], $discovery);
        self::assertSame([FirstPlugin::class, SecondPlugin::class], array_map(get_class(...), $container->get('collector')->items), 'the tagged ones in their order, the one not installed dropped');
        self::assertSame([FirstPlugin::class, SecondPlugin::class], array_map(get_class(...), $container->get('by.class')->items));
        self::assertSame($container->get(FirstPlugin::class), $container->get('collector')->items[0]);
        self::assertInstanceOf(NullLinker::class, $container->get(Linker::class), 'no candidate: the default');
        self::assertInstanceOf(FirstPlugin::class, $container->get('given'), 'a service given stays');
        self::assertFalse($container->has('nowhere'));

        $admin = Container::fromDefinitions([['class' => NullLinker::class, 'id' => 'linker.admin']], [], [], $discovery);
        self::assertSame($admin->get('linker.admin'), $admin->get(Linker::class), 'the first candidate defined');
    }
}
