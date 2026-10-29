<?php

namespace Omnibridge\Contract;

/**
 * The languages of the site, language and country joined by a dash (fr-FR,
 * en-GB): the one being answered, the default one, every one it speaks.
 *
 * Generic: every host has a current and a default locale. A bridge that names
 * them otherwise (Laravel's `fr`) gives them in this form. Named in the
 * plural so as not to shadow PHP's \Locale where it is imported.
 *
 * Formerly Base\Contract\Locales, an alias removed at omnibase 4's
 * final pass.
 */
interface Locales
{
    /** The locale of the request being answered (or of the command), fr-FR. */
    public function getLocale(): string;

    /** The site's default locale, the language its texts are written in first. */
    public function getDefaultLocale(): string;

    /**
     * Every locale the site speaks, the default first.
     *
     * @return list<string>
     */
    public function getAvailableLocales(): array;
}
