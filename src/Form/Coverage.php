<?php

namespace Omnibridge\Form;

/**
 * What a host draws of the field kinds an application uses: each kind
 * declared implemented, or unsupported with the reason. A kind a host leaves
 * undeclared is a fault, not a plain input: verify() names it, and a host's
 * renderer refuses it (UnsupportedKind).
 *
 * A host's declaration is a map kind => self::IMPLEMENTED or
 * self::unsupported('why'); omnisense/omnibase's Base\Field\Kinds reads the
 * map of each known host (Symfony's, Laravel's) and a shared conformance
 * suite runs every implemented kind on each.
 */
final class Coverage
{
    public const IMPLEMENTED = 'implemented';

    private const UNSUPPORTED = 'unsupported: ';

    private function __construct()
    {
    }

    /** The declaration of a kind a host does not draw, with its reason. */
    public static function unsupported(string $reason): string
    {
        if ('' === trim($reason)) {
            throw new \InvalidArgumentException('An unsupported kind says why.');
        }

        return self::UNSUPPORTED.$reason;
    }

    public static function isImplemented(?string $declaration): bool
    {
        return self::IMPLEMENTED === $declaration;
    }

    public static function isUnsupported(?string $declaration): bool
    {
        return null !== $declaration && str_starts_with($declaration, self::UNSUPPORTED) && '' !== self::reason($declaration);
    }

    /** Why a kind is unsupported; null for any other declaration. */
    public static function reason(?string $declaration): ?string
    {
        if (null === $declaration || !str_starts_with($declaration, self::UNSUPPORTED)) {
            return null;
        }

        return trim(substr($declaration, \strlen(self::UNSUPPORTED)));
    }

    /**
     * What is wrong with a host's declaration against the kinds that exist:
     * a kind left undeclared, a declaration that is neither implemented nor
     * unsupported with a reason, a kind declared that does not exist.
     *
     * @param list<string>          $kinds    every kind there is
     * @param array<string, string> $declared kind => declaration
     *
     * @return list<string> one sentence per fault, [] when the declaration is whole
     */
    public static function verify(array $kinds, array $declared): array
    {
        $faults = [];
        foreach ($kinds as $kind) {
            if (!\array_key_exists($kind, $declared)) {
                $faults[] = sprintf('The kind "%s" is not declared: implement it or declare it unsupported, with the reason.', $kind);
            } elseif (!self::isImplemented($declared[$kind]) && !self::isUnsupported($declared[$kind])) {
                $faults[] = sprintf('The kind "%s" is declared "%s": neither implemented nor unsupported with a reason.', $kind, $declared[$kind]);
            }
        }
        foreach (array_diff(array_keys($declared), $kinds) as $unknown) {
            $faults[] = sprintf('The kind "%s" is declared but does not exist.', $unknown);
        }

        return $faults;
    }

    /**
     * The kinds a declaration implements.
     *
     * @param array<string, string> $declared
     *
     * @return list<string>
     */
    public static function implemented(array $declared): array
    {
        return array_keys(array_filter($declared, self::isImplemented(...)));
    }
}
