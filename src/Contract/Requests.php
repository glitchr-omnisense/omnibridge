<?php

namespace Omnibridge\Contract;

/**
 * The request being answered, read without the framework's request object:
 * the visitor's address and headers, the page asked, its route, and the
 * session it carries (its values, its flash messages).
 *
 * Generic: Symfony's RequestStack (omnibridge/symfony), Laravel's request and
 * session (omnibridge/laravel). Outside a request (a command, a worker, plain
 * PHP without a host) has() is false, the readers answer null and the writers
 * do nothing.
 *
 * The session is the main request's, the rest the current one's: under
 * Symfony a sub-request (an embedded controller) has no session of its own.
 */
interface Requests
{
    /** Whether a request is being answered. */
    public function has(): bool;

    /** The visitor's address, as the host's trusted proxies give it. */
    public function ip(): ?string;

    /** A header's value (case-insensitive name), its first one when it repeats. */
    public function header(string $name): ?string;

    /** The address asked, whole: scheme, host, path and query. */
    public function uri(): ?string;

    /** The scheme and the host of the address asked, without a trailing slash (https://example.org). */
    public function host(): ?string;

    /** The name of the route answering, when the router has found it. */
    public function route(): ?string;

    /** An attribute the host or the application set on the request (the route's parameters among them). */
    public function attribute(string $name, mixed $default = null): mixed;

    /** A value the session holds. */
    public function session(string $key, mixed $default = null): mixed;

    /** A value kept in the session; null forgets it. Nothing without a session. */
    public function remember(string $key, mixed $value): void;

    /** A value the session forgets. */
    public function forget(string $key): void;

    /** A message shown once, on the next page drawn, under its type (success, warning, danger...). */
    public function flash(string $type, string $message): void;

    /**
     * The messages waiting, by type, left where they are.
     *
     * @return array<string, list<string>>
     */
    public function flashes(): array;

    /**
     * The host's own request object, for code that still reads it (Symfony's
     * Request under Symfony, Laravel's - a subclass of it - under Laravel);
     * null outside a request. Prefer the readers above.
     */
    public function request(): ?object;
}
