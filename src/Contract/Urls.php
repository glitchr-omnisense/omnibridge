<?php

namespace Omnibridge\Contract;

/**
 * Addresses, as an application asks for them: a route by its name, as a path
 * (`path()`) or whole, with the scheme and the host (`url()`, for a mail, a
 * feed, a link shared elsewhere), and a public file of the build (`asset()`).
 *
 * Generic: every host has routes with names and serves public files. Each
 * bridge answers with its own router and asset packages (omnibridge/laravel's
 * RouterUrls, omnisense/omnibase's Base\Bridge\Symfony\RouterUrls); a page
 * rendered by Twig asks the same object (omnibridge/twig's ContractExtension).
 *
 * Formerly Omnibridge\Twig\Contract\Urls and Base\Contract\Urls, which remain
 * its aliases until omnibase 4's final pass.
 */
interface Urls
{
    /** @param array<string, mixed> $parameters */
    public function path(string $name, array $parameters = []): string;

    /**
     * The same route, whole (scheme and host).
     *
     * @param array<string, mixed> $parameters
     */
    public function url(string $name, array $parameters = []): string;

    public function asset(string $path): string;
}
