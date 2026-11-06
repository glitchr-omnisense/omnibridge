# Options as data

`Omnibridge\Options\Processor` checks a configuration against a schema declared as data, without a framework: what
a host that has no Symfony Config component validates its options with (omnibridge/laravel's config, a plain-PHP
application), while omnibridge/symfony compiles the same schema into a Config tree (`Omnibridge\Symfony\Options\SchemaTree`).
Both answer alike: the same values, merged and completed with the same defaults, the same refusals with the same
message, the same deprecations. omnisense/omnibase declares its `base:` options so (`Base\Options`, plan "omnibase
agnostic", step F5e slice 4b).

## The schema

The root's children, each a node:

| Key | For |
|---|---|
| `type` | `bool`, `int`, `float`, `scalar` (string, number, boolean or null), `enum` (one of `values`), `mixed`, `array` |
| `info` | what the option is for: a refused value's hint |
| `default` | the value when the configuration says nothing; a node without one is left out. A `bool` whose default is null takes null; any other `bool` takes null as true |
| `deprecated` | `['package' => ..., 'since' => ..., 'message' => ...]`, `%node%` and `%path%` replaced, said (E_USER_DEPRECATED, `Since <package> <since>: <message>`) when the configuration sets it |
| `min`, `max` | an `int` or `float`'s bounds |
| `children` | an `array`'s named options; completed with their defaults when `defaults` is true, absent otherwise |
| `prototype` | an `array`'s items, a list or - with `key`, the attribute an item's key is read from - a map; `default_children` gives one default item when none is set, `default` the array when nothing is set, `deep_merging` false lets a later configuration replace it whole |

Keys written with a dash read as with an underscore; null and true are an empty array for an `array`.

```php
use Omnibridge\Options\Processor;

$schema = [
    'enabled' => ['type' => 'bool', 'default' => true],
    'size' => ['type' => 'int', 'default' => 3, 'min' => 0],
    'roles' => ['type' => 'array', 'prototype' => ['type' => 'scalar'], 'default' => []],
];

Processor::process($schema, 'acme', [['size' => 5], ['roles' => ['A']]]);
// ['size' => 5, 'roles' => ['A'], 'enabled' => true]
Processor::process($schema, 'acme', [['size' => -1]]);
// InvalidOption: The value -1 is too small for path "acme.size". Should be greater than or equal to 0
```

Several configurations (Symfony's `when@env`, imports) are merged in their order, the later over the earlier: a list
is appended, a map merged by key.

## Placeholders

A default may name a parameter only the host knows (`%kernel.project_dir%/var`, `%env(APP_ENV)%`).
`Options\Placeholders::resolve($value, fn (string $name) => ...)` replaces them as Symfony's container resolves a
parameter: a string that is one placeholder takes the parameter's value whatever its type, a placeholder inside a
string its text, `%%` a percent sign.
