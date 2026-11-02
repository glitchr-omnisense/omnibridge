---
title: Contracts
order: 20
---

# Contracts

Eleven interfaces under `Omnibridge\Contract\` (and the persistence's under `Omnibridge\Persistence\`, [Persistence](persistence.md)). Each says what an application asks of its host, never
how the host answers: a bridge implements it over its framework's services
([which bridge answers which](index.md#the-contracts)). Every one of them was
`Base\Contract\<Name>` in omnisense/omnibase before; those former names were removed at omnibase 4's
final pass ([Former names](index.md#former-names)).

The application reaches a contract through the registry (`Runtime::urls()`...,
[The registry](registry.md)) or, inside a framework, by autowiring the interface: both bridges bind
every adapter under its interface's name.

## Urls

Addresses, as an application asks for them: a route by its name, as a path or whole, and a public
file of the build.

| Method | Returns | Meaning |
|---|---|---|
| `path(string $name, array $parameters = [])` | `string` | the route's path (`/articles/le-fuji`), the parameters filled in as the host's router fills them |
| `url(string $name, array $parameters = [])` | `string` | the same route whole, with the scheme and the host (a mail, a feed, a link shared elsewhere) |
| `asset(string $path)` | `string` | the public address of a file of the build (`build/app.css`), through the host's asset packages |

A route that does not exist is the host router's error (Symfony's `RouteNotFoundException`,
Laravel's `RouteNotFoundException` or `InvalidArgumentException`): the contract adds none of its
own. Answered by `Omnibridge\Symfony\RouterUrls` and `Omnibridge\Laravel\RouterUrls`; a Twig page
asks the same object through omnibridge/twig's `path()`, `url()` and `asset()`.

## Translations

The application's texts: a message in the page's language, from its catalogues (ICU messages:
`{0}` and `{name}` placeholders, plurals), and whether the catalogues hold a key at all.

| Method | Returns | Meaning |
|---|---|---|
| `trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null)` | `string` | the message, its parameters substituted; the page's locale when none is given; the host's default domain when none is given |
| `exists(string $id, ?string $domain = null, ?string $locale = null)` | `bool` | whether the catalogue of that locale (the page's when none is given) holds the key in that domain, its fallback catalogues included |

`exists()` is for code that tries several keys in turn (an entity's name in a namespace, then in
its parent's). Formerly two interfaces: `Omnibridge\Twig\Contract\Translations` (with `trans()`
alone) and `Base\Contract\Translations` (with `exists()`), both removed since; now one, so one object answers a page and
the application's code alike. Answered by `Omnibridge\Symfony\TranslatorTranslations` and
`Omnibridge\Laravel\CatalogueTranslations` (which reads ICU catalogues with symfony/translation).

## Locales

The languages of the site, language and country joined by a dash (`fr-FR`, `en-GB`). A bridge
whose host names them otherwise (Laravel's `fr`) gives them in this form. Named in the plural so as
not to shadow PHP's `\Locale` where it is imported.

| Method | Returns | Meaning |
|---|---|---|
| `getLocale()` | `string` | the locale of the request being answered, or of the command |
| `getDefaultLocale()` | `string` | the site's default locale, the language its texts are written in first |
| `getAvailableLocales()` | `list<string>` | every locale the site speaks, the default first |

Answered by `Omnibridge\Laravel\Contract\AppLocales`; under Symfony by omnisense/omnibase's
`Base\Bridge\Symfony\LocalizerLocales` (omnibridge/symfony binds none: an application's languages
are its own).

## Access

What the visitor may do: an attribute (a role, `ROLE_ADMIN`, or a permission a policy answers,
`edit` on a subject) granted or not, and who the visitor is.

| Method | Returns | Meaning |
|---|---|---|
| `isGranted(mixed $attribute, mixed $subject = null)` | `bool` | whether the visitor holds the attribute, on the subject when one is given; false for nobody signed in unless the attribute is granted to anyone |
| `getUser()` | `?object` | the visitor signed in (the application's user), null for nobody |

Symfony's authorization checker and token storage (`Omnibridge\Symfony\Contract\SecurityAccess`),
Laravel's gate and guard (`Omnibridge\Laravel\Contract\GateAccess`) answer the same two questions.

## Sessions

The visitor's session, as the application ends it.

| Method | Returns | Meaning |
|---|---|---|
| `signOut(object $user)` | `bool` | signs the user out if it is the visitor signed in here (the session forgotten, a remember-me cookie cleared) and answers true; false when it is not (nobody, or someone else), and nothing is done |

omnisense/omnibase's `User::logout()` asks it first, and kicks the user out of its sessions elsewhere when it answers false. Symfony's token storage
(`SecuritySessions`), Laravel's guard `logout()` (`GuardSessions`).

## Requests

The request being answered, read without the framework's request object (`Runtime::requests()`).

| Method | Returns | Meaning |
|---|---|---|
| `has()` | `bool` | whether a request is being answered (false in a command, a worker, plain PHP) |
| `ip()` | `?string` | the visitor's address, as the host's trusted proxies give it |
| `header(string $name)` | `?string` | a header's first value, the name case-insensitive |
| `uri()` | `?string` | the address asked, whole |
| `host()` | `?string` | its scheme and host, no trailing slash (`https://example.org`) |
| `route()` | `?string` | the name of the route answering, once the router has found it |
| `attribute(string $name, mixed $default = null)` | `mixed` | an attribute set on the request (the route's parameters among them) |
| `session(string $key, mixed $default = null)` | `mixed` | a value the session holds |
| `remember(string $key, mixed $value)` | `void` | a value kept in the session; null forgets it |
| `forget(string $key)` | `void` | a value forgotten |
| `flash(string $type, string $message)` | `void` | a message shown once on the next page, under its type (success, warning...) |
| `flashes()` | `array<string, list<string>>` | the messages waiting, left where they are |
| `request()` | `?object` | the host's own request (Symfony's, or Laravel's, a subclass of it), for code that still reads it |

The session is the main request's, the rest the current one's. Outside a request the readers answer
null and the writers do nothing. Symfony's request stack (`StackRequests`, omnibridge/symfony),
Laravel's request and session (`LaravelRequests`, omnibridge/laravel: the flashes where its pages read
omnibase's flash bag).

## Roles

The roles some roles reach in the host's hierarchy (`Runtime::roles()`).

| Method | Returns | Meaning |
|---|---|---|
| `reachable(array $roles)` | `list<string>` | the roles given and every role below them (`ROLE_ADMIN` reaching `ROLE_STAFF`, `ROLE_USER`) |

Symfony's role hierarchy (`HierarchyRoles`, omnibridge/symfony), Laravel's `config/omniauth.php`'s `role_hierarchy`
(`Auth\Roles`, omnibridge/laravel). omnisense/omnibase's `SecurityPolicy` asks it for the roles a second factor is
required of, and the administration's.

## Tokens

The token a form carries for an intent, against cross-site requests: what a page's POST (or a
script's request) sends back and its action checks.

| Method | Returns | Meaning |
|---|---|---|
| `csrf(string $intent)` | `string` | the token of that intent (`profile_edit`, `delete_12`...) for the visitor's session |
| `isValid(string $intent, ?string $token)` | `bool` | whether a token sent back is the visitor's token of that intent: what an action checks before acting; a missing or empty token is never valid |

Symfony's CSRF token manager has one token per intent (`CsrfTokens`); Laravel's session token is
one for every intent (`SessionTokens`). A token made by one host is checked by that host only: a
page rendered by Symfony posts to Symfony.

## Files

The stored files as the application sees them: where a file is served from and where PHP can read
it, by its path in a storage. Nothing of how it is stored (Flysystem, a disk, a bucket).

A storage is named as the application's configuration names it (`local.uploads`, `s3.private`...),
null for the default one; each bridge maps those names to its host's storages.

| Method | Returns | Meaning |
|---|---|---|
| `publicPath(string $path, ?string $storage = null)` | `?string` | the file's path under the public directory the web server serves, or for a remote storage the address it is served from; null when it has neither (a missing file, a private storage) |
| `publicUrl(string $path, ?string $storage = null)` | `?string` | the web address of the file (`/uploads/...`); null as `publicPath()` |
| `localPath(string $path, ?string $storage = null)` | `?string` | the file's path on this machine (a local storage, its root prefixed); null where no local file is behind it (a remote storage) |
| `isRemote(?string $storage = null)` | `bool` | whether the storage is remote: no file on this machine behind its paths |

Answered by `Omnibridge\Laravel\Contract\DiskFiles` (Laravel's disks); under Symfony by
omnisense/omnibase's `Base\Bridge\Symfony\FlysystemFiles`.

## Routes

What the routes are beyond their addresses (`Urls` makes those).

| Method | Returns | Meaning |
|---|---|---|
| `pattern(string $name)` | `?string` | the path the route is declared with (`/articles/{slug}`), null for no such route |
| `match(string $url)` | `?string` | the name of the route an address leads to - a localized route's with its language (`article.en`) when it is not the default one -, null when none does |
| `icons(string $name)` | `list<string>\|null` | the icons the route is declared with, in the page's language when it has several; null for none |

Icons are a route's metadata where the host keeps some (omnibase's `#[Iconize]` under Symfony); a
host without answers null. Answered by `Omnibridge\Laravel\Contract\RouterRoutes`; under Symfony by
omnisense/omnibase's `Base\Bridge\Symfony\RouterRoutes`.

## Environment

What the application runs as.

| Method | Returns | Meaning |
|---|---|---|
| `name()` | `string` | the environment's name: `prod`, `dev`, `test`... |
| `isDebug()` | `bool` | whether it runs in debug |
| `secret()` | `string` | the application's secret, which salts what it hashes |

Symfony's `kernel.environment`, `kernel.debug` and `kernel.secret` (`KernelEnvironment`); Laravel's
`APP_ENV`, `app.debug` and `app.key` (`AppEnvironment`).

## The clock and the events

No contract of their own: the PSR interfaces are already generic.

- `Psr\Clock\ClockInterface`, asked by `Runtime::clock()`. A host gives its own (symfony/clock's,
  which a test can freeze; omnibridge/laravel's `DateClock` over Laravel's dates). When none was
  given, the registry answers `Omnibridge\SystemClock`, whose `now()` is
  `new \DateTimeImmutable()` - the machine's time in PHP's default time zone.
- `Psr\EventDispatcher\EventDispatcherInterface`, asked by `Runtime::events()`: Symfony's
  `event_dispatcher`, omnibridge/laravel's `DispatcherEvents` over Laravel's dispatcher. There is no
  fallback: asked and not given, it throws `MissingContractException`.
- An event known by a name as well (omnibase's `user.register`) implements `Omnibridge\Event\NamedEvent`
  and is dispatched by `Omnibridge\Event\Events::dispatch($dispatcher, $event, $name)`: a dispatcher whose
  `dispatch()` takes a second argument (Symfony's) is given the name, any other the event alone;
  omnibridge/laravel's `DispatcherEvents` gives a NamedEvent to the listeners of its name as well.

## Views

A view rendered by its name, in whatever view technology the application chose (Twig, Blade, a
React page's server side): a mail's body, a screen, a fragment. Since omnibase's step F1
(2026-11-01). Answered by omnibridge/twig's `TwigViews` over Twig's `Environment` (omnibase's step
F5a, 2026-11-02: omnibase's notifier, mails, sitemap, QR sheet, sharing buttons and media tags
render through it under Symfony and Laravel alike); Laravel's view factory (Blade) is planned.

| Method | Returns | Meaning |
|---|---|---|
| `render(string $name, array $context = [])` | `string` | the view rendered with that context |
| `exists(string $name)` | `bool` | whether the host's engine knows a view of that name |

`Runtime::views()`.

### ViewSources

Beside `Views`, optional: where the engine finds a file by its name - what an e-mail embeds as an
inline picture (`email.image('@Public/logo.png')`). A caller checks `$views instanceof ViewSources`
and does without when it is not.

| Method | Returns | Meaning |
|---|---|---|
| `sourcePath(string $name)` | `?string` | the file's path on disk, null when the engine holds it in memory |
| `sourceCode(string $name)` | `string` | the file's contents |

## Validation

A value checked against constraints, by the host's validator: a form's processing, an import, an
action, before anything is kept. Since omnibase's step F1 (2026-11-01); answered by
symfony/validator (omnibridge/symfony), Laravel's validator (omnibridge/laravel) and, in plain PHP,
`Omnibridge\Validation\Validator` for the kept constraints (below).

| Method | Returns | Meaning |
|---|---|---|
| `validate(mixed $value, array $constraints = [], ?array $groups = null)` | `list<Violation>` | an object against the constraints it declares plus those given, a scalar or an array against those given; empty when valid |

A `Omnibridge\Contract\Violation` has the `path` (`""` for the value itself), the `message` (the
host's translation), the constraint's `code` and the message's `parameters`.
`Runtime::validation()`.

### The kept constraints and the plain-PHP validator

`Omnibridge\Validation\Constraint\*` are the constraints every host validates (omnibase's plan, step
F5b): `NotBlank`, `Length`, `Email`, `Regex`, `Choice`, `Range`, `File`, `Unique`. Plain objects, and
attributes on a property (`Unique` on a class too), with Symfony's own messages and codes, so a form
says the same everywhere; each has its `groups` (`Default` by default).

`Omnibridge\Validation\Validator` validates them in plain PHP: an object against its attributes (its
class's and its properties', parents included), a value against those given; a constraint it does
not know is refused aloud (`InvalidArgumentException`). Messages: Symfony's templates, the parameters
replaced, the plural form chosen by `{{ limit }}`; a `$translate` callable (template, parameters)
translates them instead. `only()` validates against the constraints given alone, `uniques()` runs what a
record declares of `Unique`, for a host whose own validator reads the rest. `Unique` asks the `$exists` callable - `(string $class, array $criteria,
?object $record): bool`, whether another record has those values - and is refused without it.

```php
$validator = new Validator(exists: fn (string $class, array $criteria, ?object $record): bool => ...);
$validator->validate('ab', [new Length(min: 3)]);   // [Violation('', 'This value is too short. It should have 3 characters or more.', Length::TOO_SHORT, [...])]
```

symfony/validator (omnibridge/symfony's `SymfonyValidation`, `Unique` left to this validator) and
Laravel's validator (omnibridge/laravel) read the same constraints.

## Writing an adapter

A host that has no bridge yet implements the interfaces it can answer and fills the registry
([The registry](registry.md#filling-the-registry)). An adapter:

- answers for the current request or command, not for the one it was built in: a long-running
  worker builds it once (read the request, the locale, the user when called, not in the
  constructor);
- throws its framework's own exception for what the framework refuses (an unknown route); the
  contract adds none;
- gives locales as `xx-XX`, whatever its host's own form.

An application that runs on two hosts asserts the same answers on both: lesringostars' parity suite
renders the same pages through both bridges and compares them.
