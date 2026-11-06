<?php

namespace Omnibridge\Options;

/**
 * The parameters a value names ('%kernel.project_dir%/public/maintenance.lock', '%env(APP_ENV)%'), replaced as
 * Symfony's container resolves a parameter's value: a string that is one placeholder takes the parameter's value
 * whatever its type, a placeholder inside a string its text, '%%' a percent sign; an array item by item, its keys
 * as they are. What a host does with a schema's defaults (Options\Processor) once it knows its parameters.
 */
final class Placeholders
{
    /**
     * @param \Closure(string $name): mixed $parameter a parameter's value by its name ('kernel.cache_dir', 'env(NAME)')
     */
    public static function resolve(mixed $value, \Closure $parameter): mixed
    {
        if (\is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::resolve($item, $parameter), $value);
        }
        if (!\is_string($value) || !str_contains($value, '%')) {
            return $value;
        }
        if (preg_match('/^%([^%\s]+)%$/', $value, $match)) {
            return $parameter($match[1]);
        }

        return preg_replace_callback('/%%|%([^%\s]+)%/', static function (array $match) use ($parameter): string {
            if ('%%' === $match[0]) {
                return '%';
            }
            $resolved = $parameter($match[1]);
            if (null !== $resolved && !\is_scalar($resolved)) {
                throw new \InvalidArgumentException(\sprintf('The parameter "%s" is not a scalar: it cannot be written inside a string.', $match[1]));
            }

            return (string) $resolved;
        }, $value);
    }
}
