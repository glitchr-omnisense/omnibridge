<?php

namespace Omnibridge;

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
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The registry through which an application's framework-free code reaches the
 * contracts its host answers: an entity's getter, a value object, a screen's
 * builder asks Runtime::urls(), ::access()... instead of a framework's
 * services.
 *
 * Each contract is asked by its interface's name. A host fills the registry
 * once, at boot:
 *
 * - with a PSR-11 container whose ids are those names (setContainer()): under
 *   Symfony, a service locator (omniscient/omnibase's `base.contracts`); under
 *   Laravel, the application itself, its bindings named by the interfaces
 *   (omnibridge/laravel);
 * - or one service at a time (set()), which wins over the container's.
 *
 * A contract is one interface whatever name it is asked by: an alias kept for
 * a former name (Base\Contract\Urls, Omnibridge\Twig\Contract\Urls) is the
 * same contract, the same service. The container is asked by the interface's
 * own name first, then by the name asked.
 *
 * A contract asked and not filled throws MissingContractException, except the
 * clock, which is the machine's (SystemClock) when none was given; find()
 * answers null instead, for a caller that can do without.
 *
 * A different container means a different kernel: what was resolved from the
 * previous one is forgotten, the services set by hand are kept.
 *
 * Not final: an application family adds its own contracts' accessors in a
 * class of its own over the same registry (omniscient/omnibase's
 * Base\Contract\Runtime: its parameters, nouns, media, pictures, texts and
 * mailer). The state is this class's, shared by any such class.
 */
class Runtime
{
    private static ?ContainerInterface $container = null;

    /** @var array<string, object> set by hand, ahead of the container, by the contract's own name */
    private static array $given = [];

    /** @var array<string, object> resolved from the container, once each, by the contract's own name */
    private static array $resolved = [];

    /** @var array<string, string> a name asked => the contract's own name (an alias resolved) */
    private static array $names = [];

    public static function setContainer(?ContainerInterface $container): void
    {
        if ($container !== self::$container) {
            self::$resolved = [];
        }
        self::$container = $container;
    }

    public static function getContainer(): ?ContainerInterface
    {
        return self::$container;
    }

    /**
     * One contract's service, ahead of the container's; null takes it back.
     *
     * @param class-string $contract
     */
    public static function set(string $contract, ?object $service): void
    {
        $name = self::name($contract);
        if (null === $service) {
            unset(self::$given[$name], self::$resolved[$name]);

            return;
        }
        if (!$service instanceof $contract) {
            throw MissingContractException::notAn($contract, $service);
        }
        self::$given[$name] = $service;
    }

    /** @param class-string $contract */
    public static function has(string $contract): bool
    {
        $name = self::name($contract);

        return isset(self::$given[$name]) || isset(self::$resolved[$name]) || null !== self::id($name, $contract);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $contract
     *
     * @return T|null
     */
    public static function find(string $contract): ?object
    {
        $name = self::name($contract);
        if (isset(self::$given[$name])) {
            return self::$given[$name];
        }
        if (isset(self::$resolved[$name])) {
            return self::$resolved[$name];
        }
        $id = self::id($name, $contract);
        if (null === $id) {
            return null;
        }

        $service = self::$container->get($id);
        if (!$service instanceof $contract) {
            throw MissingContractException::notAn($contract, $service);
        }

        return self::$resolved[$name] = $service;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $contract
     *
     * @return T
     */
    public static function get(string $contract): object
    {
        return self::find($contract) ?? throw MissingContractException::for($contract);
    }

    /** Everything forgotten: no container, nothing set (a test's tearDown). */
    public static function reset(): void
    {
        self::$container = null;
        self::$given = [];
        self::$resolved = [];
    }

    public static function urls(): Urls
    {
        return self::get(Urls::class);
    }

    public static function translations(): Translations
    {
        return self::get(Translations::class);
    }

    public static function locales(): Locales
    {
        return self::get(Locales::class);
    }

    public static function access(): Access
    {
        return self::get(Access::class);
    }

    /** The visitor's session: a user signed out when it is the one signed in. */
    public static function sessions(): Sessions
    {
        return self::get(Sessions::class);
    }

    public static function tokens(): Tokens
    {
        return self::get(Tokens::class);
    }

    public static function files(): Files
    {
        return self::get(Files::class);
    }

    /** What the routes are beyond their addresses: a route's path, an address's route, a route's icons. */
    public static function routes(): Routes
    {
        return self::get(Routes::class);
    }

    /** What the application runs as: its environment's name, debug, its secret. */
    public static function environment(): Environment
    {
        return self::get(Environment::class);
    }

    /** The host's clock, or the machine's when none was given. */
    public static function clock(): ClockInterface
    {
        return self::find(ClockInterface::class) ?? self::$resolved[ClockInterface::class] = new SystemClock();
    }

    public static function events(): EventDispatcherInterface
    {
        return self::get(EventDispatcherInterface::class);
    }

    /** The contract's own name: an alias's original, the name itself for anything else. */
    private static function name(string $contract): string
    {
        return self::$names[$contract] ??= interface_exists($contract) || class_exists($contract)
            ? (new \ReflectionClass($contract))->getName()
            : $contract;
    }

    /** The container's id for the contract, by its own name first, then by the name asked; null when it has neither. */
    private static function id(string $name, string $asked): ?string
    {
        if (null === self::$container) {
            return null;
        }
        if (self::$container->has($name)) {
            return $name;
        }

        return $asked !== $name && self::$container->has($asked) ? $asked : null;
    }
}
