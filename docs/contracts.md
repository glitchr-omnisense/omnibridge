---
title: Contracts
order: 20
---

# Contracts

Nine interfaces under `Omnibridge\Contract\`. Each says what an application asks of its host, never
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
