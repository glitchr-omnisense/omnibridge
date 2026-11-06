<?php

namespace Omnibridge\Persistence;

use Omnibridge\Exception\PersistenceException;

/**
 * Which persistence an application runs on, found by what is installed (omnisense/omnibase's plan "omnibase
 * agnostic", step F7d; risk R8): one persistence bridge installed, it is used; several, the host's configuration
 * names one - `base.persistence` (config/packages/base.yaml under Symfony, config/base.php under Laravel), the
 * parameter `persistence` of Omnibridge\Container in plain PHP - and none named is refused aloud, never one picked
 * silently; with several installed and the option naming none, the environment variable OMNIBRIDGE_PERSISTENCE may
 * (a harness loading both, a command run once on the other persistence). Swapping is then one click: `composer remove omnibridge/doctrine && composer require omnibridge/eloquent`,
 * or the option.
 *
 * A persistence is installed when its marker class can be loaded: a class at the root of its bridge, which needs
 * neither omnisense/omnibase nor a host (omnibridge/doctrine's EntityManagers, omnibridge/eloquent's
 * EloquentUnitOfWork). Its container `definitions` (a static method returning services declared as data,
 * Omnibridge\Container\Declarations' format) are what Omnibridge\Container adds when it is chosen; a persistence
 * declaring none is given by the application (its unit of work, as a service).
 */
final class Persistences
{
    public const DOCTRINE = 'doctrine';
    public const ELOQUENT = 'eloquent';

    /** The environment variable naming the persistence where several are installed and the host's option names none. */
    public const ENVIRONMENT = 'OMNIBRIDGE_PERSISTENCE';

    /** @var array<string, array{package: string, marker: class-string, definitions?: string}> */
    public const KNOWN = [
        self::DOCTRINE => ['package' => 'omnibridge/doctrine', 'marker' => 'Omnibridge\Doctrine\EntityManagers'],
        self::ELOQUENT => ['package' => 'omnibridge/eloquent', 'marker' => 'Omnibridge\Eloquent\EloquentUnitOfWork', 'definitions' => 'Omnibridge\Eloquent\Container::definitions'],
    ];

    /** @var array<string, array{package: string, marker: string, definitions?: string}> another persistence's, by name */
    private static array $registered = [];

    /**
     * A persistence of another package (its name, the package, the marker class, its container definitions).
     *
     * @param string|null $definitions `Class::method`, a static method returning services declared as data
     */
    public static function register(string $name, string $package, string $marker, ?string $definitions = null): void
    {
        self::$registered[$name] = ['package' => $package, 'marker' => $marker] + (null !== $definitions ? ['definitions' => $definitions] : []);
    }

    /** A persistence registered by register() forgotten (a test's). */
    public static function unregister(string $name): void
    {
        unset(self::$registered[$name]);
    }

    /** @return array<string, array{package: string, marker: string, definitions?: string}> every persistence known, by name */
    public static function known(): array
    {
        return self::$registered + self::KNOWN;
    }

    /** @return list<string> the names of the persistences installed, in their order */
    public static function installed(): array
    {
        $installed = [];
        foreach (self::known() as $name => $persistence) {
            if (self::loadable($persistence['marker'])) {
                $installed[] = $name;
            }
        }

        return $installed;
    }

    public static function isInstalled(string $name): bool
    {
        $persistence = self::known()[$name] ?? null;

        return null !== $persistence && self::loadable($persistence['marker']);
    }

    /**
     * The persistence to run on: the one configured, which must be installed; else the only one installed; null when
     * none is installed and none is asked.
     *
     * @param string|null $configured the host's option (null or '' when it names none)
     * @param string      $option     where the host reads it, named in a refusal ('base.persistence in config/packages/base.yaml')
     *
     * @throws PersistenceException a name no persistence has, a persistence not installed, several installed and none named
     */
    public static function choose(?string $configured, string $option = 'the option "persistence"'): ?string
    {
        // The host's option first; else, with several installed, the environment's (a harness loading both, a command
        // run once on the other persistence) - with one installed or none it is not read.
        if ((null === $configured || '' === $configured) && \count(self::installed()) > 1) {
            $environment = getenv(self::ENVIRONMENT);
            if (\is_string($environment) && '' !== $environment) {
                $configured = $environment;
                $option = self::ENVIRONMENT;
            }
        }
        $known = self::known();
        if (null !== $configured && '' !== $configured) {
            if (!isset($known[$configured])) {
                throw new PersistenceException(sprintf('No persistence is named "%s" (%s): %s.', $configured, $option, self::names(array_keys($known))));
            }
            // Only the one named is looked for: nothing of another persistence is loaded.
            if (!self::isInstalled($configured)) {
                throw new PersistenceException(sprintf('The persistence "%s" (%s) is not installed: composer require %s.', $configured, $option, $known[$configured]['package']));
            }

            return $configured;
        }
        $installed = self::installed();
        if (\count($installed) > 1) {
            throw new PersistenceException(sprintf('%s are installed: %s (or the environment variable '.self::ENVIRONMENT.') names the one to run on (%s), none is picked for you.', self::names(array_map(static fn (string $name): string => $known[$name]['package'], $installed)), $option, implode('|', $installed)));
        }

        return $installed[0] ?? null;
    }

    /**
     * The services the persistence declares for a container (Omnibridge\Container\Declarations' format), none when it
     * declares none or is not installed.
     *
     * @return list<array<string, mixed>>
     */
    public static function definitions(string $name): array
    {
        $persistence = self::known()[$name] ?? null;
        if (null === $persistence || !isset($persistence['definitions']) || !self::loadable($persistence['marker'])) {
            return [];
        }
        [$class, $method] = explode('::', $persistence['definitions'], 2);
        if (!self::loadable($class)) {
            return [];
        }

        return array_values([...$class::$method()]);
    }

    /** @param list<string> $names */
    private static function names(array $names): string
    {
        if (\count($names) < 2) {
            return implode('', $names);
        }

        return implode(', ', \array_slice($names, 0, -1)).' and '.$names[\count($names) - 1];
    }

    /**
     * Whether a class can be loaded, without loading it where Composer's class loaders can tell (a marker is looked for,
     * not loaded: nothing of a persistence's bridge is loaded to know it is there); with an autoloader of another kind,
     * by loading it.
     */
    private static function loadable(string $class): bool
    {
        if (class_exists($class, false) || interface_exists($class, false)) {
            return true;
        }
        $composer = false;
        foreach (spl_autoload_functions() as $function) {
            if (\is_array($function) && $function[0] instanceof \Composer\Autoload\ClassLoader) {
                $composer = true;
                if (false !== $function[0]->findFile($class)) {
                    return true;
                }
            }
        }
        if ($composer) {
            return false;
        }
        try {
            return class_exists($class);
        } catch (\Throwable) {
            return false;
        }
    }
}
