<?php

namespace Omnibridge\Form;

/**
 * What a rich field does, whatever host draws it: the contract one behaviour
 * of a form field answers (a date picker, a slug, a select with a remote
 * search, an upload), not one host's class. A behaviour holds the logic a
 * host's form type held - its options and their defaults, the vars its
 * widget reads, how a value is shown and read back, the front end it needs,
 * the host's routes it calls - as plain data, so that every host's renderer
 * and binder ask the same object (omnisense/omnibase's Base\Field\Behaviour\*;
 * omnibridge/symfony's form types and omnibridge/laravel's FormRenderer call
 * them).
 *
 * Options in and out are plain data: strings, numbers, booleans, lists and
 * maps of them. A behaviour knows no request, no form object, no framework.
 */
interface FieldBehaviour
{
    /**
     * The options resolved: the given ones over the behaviour's defaults,
     * normalized (what a Symfony type's configureOptions() gave).
     *
     * @param array<string, mixed> $given
     *
     * @return array<string, mixed>
     */
    public function options(array $given): array;

    /**
     * What the widget reads beyond a field's generic vars (name, id, value,
     * label, errors...): its settings, as plain data (a JSON string where the
     * widget's script reads one from an attribute).
     *
     * @param array<string, mixed> $options resolved (options())
     * @param mixed                $shown   the value shown (transform()'s)
     * @param string               $id      the field's id in the page, for a script told where its element is
     *
     * @return array<string, mixed> a key `attr` is merged into the field's attributes
     */
    public function vars(array $options, mixed $shown, string $id): array;

    /**
     * The data's value as the form shows it (a string, a list of strings,
     * null).
     *
     * @param array<string, mixed> $options resolved
     */
    public function transform(mixed $data, array $options): mixed;

    /**
     * The submitted value as the data keeps it.
     *
     * @param array<string, mixed> $options resolved
     *
     * @throws \UnexpectedValueException a value that cannot be read: a violation of the field
     */
    public function reverse(mixed $submitted, array $options): mixed;

    /**
     * The front end the widget needs: its script's entry and the prefix of
     * the data attributes its controller reads.
     *
     * @param array<string, mixed> $options resolved
     */
    public function assets(array $options): FieldAssets;

    /**
     * The host's routes the widget calls (a search, an upload), by name; a
     * host that has no such route draws the widget without it, and says so
     * in its coverage.
     *
     * @param array<string, mixed> $options resolved
     *
     * @return array<string, Endpoint>
     */
    public function endpoints(array $options): array;
}
