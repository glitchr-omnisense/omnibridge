<?php

namespace Omnibridge\Contract;

/**
 * What the application's routes are, beyond making their addresses (Urls
 * does that): the path a route is declared with, the route an address leads
 * to, the icons a route is declared with.
 *
 * Generic: every router knows a route's pattern and matches an address. Icons
 * are a route's metadata where the host keeps some (omnibase's #[Iconize]
 * under Symfony); a host without answers null.
 *
 * Formerly Base\Contract\Routes, an alias removed at omnibase 4's
 * final pass.
 */
interface Routes
{
    /** The path the route is declared with ("/articles/{slug}"), null for no such route. */
    public function pattern(string $name): ?string;

    /**
     * The name of the route an address leads to (a localized route's with its
     * language, "article.en", when it is not the default one), null when none
     * does.
     */
    public function match(string $url): ?string;

    /**
     * The icons of a route, in the page's language when it has several; null
     * for none.
     *
     * @return list<string>|null
     */
    public function icons(string $name): ?array;
}
