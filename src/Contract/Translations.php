<?php

namespace Omnibridge\Contract;

/**
 * The application's texts: a message in the page's language, from its
 * catalogues (ICU messages: `{0}` and `{name}` placeholders, plurals), and
 * whether the catalogues hold a key at all, which a code that tries several
 * keys in turn needs.
 *
 * Generic: every host has message catalogues and can tell whether one holds a
 * key (Symfony's catalogue, Laravel's Translator::has()). omnibridge/laravel's
 * CatalogueTranslations reads the ICU catalogues with symfony/translation;
 * omnisense/omnibase's Base\Bridge\Symfony\TranslatorTranslations is its
 * translator.
 *
 * Formerly Omnibridge\Twig\Contract\Translations (trans() alone) and
 * Base\Contract\Translations (with exists()), aliases removed at omnibase 4's
 * final pass: one interface, so one object answers a page and the
 * application's code alike.
 */
interface Translations
{
    /** @param array<int|string, mixed> $parameters */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string;

    /**
     * Whether the catalogue of that locale (the page's when none is given)
     * holds the key in that domain, its fallback catalogues included.
     */
    public function exists(string $id, ?string $domain = null, ?string $locale = null): bool;
}
