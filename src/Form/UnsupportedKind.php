<?php

namespace Omnibridge\Form;

/**
 * A host was asked to draw a field kind it declares unsupported, or does not
 * declare at all: refused aloud, never a plain input in its place.
 */
final class UnsupportedKind extends \LogicException
{
    public static function of(string $kind, string $host, ?string $reason = null): self
    {
        return new self(null !== $reason
            ? sprintf('%s does not draw the field kind "%s": %s', $host, $kind, $reason)
            : sprintf('%s does not declare the field kind "%s": implement it or declare it unsupported, with the reason.', $host, $kind));
    }
}
