<!--
Read when: a field kind behaves differently on two hosts, a host draws a plain input where a rich widget
belongs, or a new kind needs each host's word.
-->

# Field behaviours and their coverage

`Omnibridge\Form\` holds what every host agrees on about a form field's behaviour, without a form
library: the contract of one behaviour, its front end, the routes it calls, and what a host declares
it draws. omnisense/omnibase implements the behaviours (`Base\Field\Behaviour\*`) and runs the
conformance suite (`Base\Field\Conformance\Suite`); each bridge draws them.

## FieldBehaviour

One behaviour, not one host's class: a date picker, a slug, a select searched remotely.

| Method | Says |
|---|---|
| `options(array $given): array` | the given options over the behaviour's defaults, normalized |
| `vars(array $options, mixed $shown, string $id): array` | what the widget reads, plain data; a key `attr` is merged into the field's attributes |
| `transform(mixed $data, array $options): mixed` | the data's value as the form shows it |
| `reverse(mixed $submitted, array $options): mixed` | the submitted value as the data keeps it; `\UnexpectedValueException` for one it cannot read |
| `assets(array $options): FieldAssets` | the script's entry (`form.datetime`) and the prefix of the data attributes its controller reads (`datetimepicker`) |
| `endpoints(array $options): array` | name => `Endpoint`: the host's routes it calls (`search`: `ux_autocomplete`) |

Options in and out are plain data. A host answers an `Endpoint`'s address through `Contract\Urls`, or
has no such route (`Contract\Routes::pattern()` null): it then refuses the widget aloud rather than
draw it without what makes it work.

## Coverage

A host declares every kind: `Coverage::IMPLEMENTED`, or `Coverage::unsupported('why')`, the reason
required.

```php
public const COVERAGE = [
    'datetime_picker' => Coverage::IMPLEMENTED,
    'cropper' => 'unsupported: the cropper is not drawn here yet',
];
```

`Coverage::verify($kinds, $declared)` names a kind left undeclared, a declaration without its reason,
a kind declared that does not exist; `Coverage::implemented($declared)` lists the implemented ones. A
renderer asked to draw a kind it does not implement throws `UnsupportedKind`: never a plain input in
its place.
