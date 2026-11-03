<?php

namespace Omnibridge\Form;

/**
 * A route of the host a field's widget calls: a select's remote search, an
 * upload. Named as the host's router names it, with its parameters and the
 * method the widget sends; each host answers its address through
 * Contract\Urls, or has no such route (Contract\Routes::pattern() null) and
 * draws the widget without it.
 */
final readonly class Endpoint
{
    /** @param array<string, mixed> $parameters */
    public function __construct(
        public string $route,
        public array $parameters = [],
        public string $method = 'GET',
    ) {
    }
}
