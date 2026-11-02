<?php

namespace Omnibridge\Contract;

/**
 * Where a view engine finds a file by its name, beside Views: what an
 * e-mail embeds as an inline picture (`email.image('@Public/logo.png')`), a
 * file of the templates' paths. Optional: a Views that cannot say answers
 * null, and the caller does without.
 *
 * Generic: Twig's loader (omnibridge/twig's TwigViews).
 */
interface ViewSources
{
    /** The file's path on disk, null when the engine has none for it (a template held in memory). */
    public function sourcePath(string $name): ?string;

    /** The file's contents, as the engine reads it. */
    public function sourceCode(string $name): string;
}
