<?php

namespace Omnibridge\Contract;

/**
 * One constraint a value breaks, as Validation reports it: where (a property
 * path, "" for the value itself), the message (translated by the host), the
 * constraint's code when it has one, and the message's parameters.
 */
final class Violation
{
    /** @param array<string, mixed> $parameters */
    public function __construct(
        public readonly string $path,
        public readonly string $message,
        public readonly ?string $code = null,
        public readonly array $parameters = [],
    ) {
    }
}
