<?php

namespace Omnibridge\Contract;

/**
 * A view rendered by its name: what an application's code asks when it needs
 * markup it does not write itself - a mail's body, a screen, a fragment - with
 * whatever view technology the application chose (Twig, Blade, a React page's
 * server side...). The application names the view; the host's engine finds it.
 *
 * Generic: Twig's environment (omnibridge/twig, under Symfony or Laravel),
 * Laravel's view factory (Blade).
 */
interface Views
{
    /**
     * The view rendered with that context.
     *
     * @param array<string, mixed> $context
     */
    public function render(string $name, array $context = []): string;

    /** Whether the host's engine knows a view of that name (an optional template, a fallback chain). */
    public function exists(string $name): bool;
}
