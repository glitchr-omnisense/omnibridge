<?php

namespace Omnibridge\Form;

/**
 * The front end of a field's widget: the script's entry a page loads for it
 * (a webpack entry, "form.datetime"), and the prefix of the data attributes
 * its controller reads (data-datetimepicker-field, data-slug-target...).
 * Both null for a plain input.
 */
final readonly class FieldAssets
{
    public function __construct(
        public ?string $entry = null,
        public ?string $controller = null,
    ) {
    }

    public static function none(): self
    {
        return new self();
    }
}
