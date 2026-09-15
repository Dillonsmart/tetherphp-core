# Changelog

All notable changes to `dillonsmart/tetherphp-core`. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project is pre-1.0: under the rule in `docs/agents/releasing.md`, a **minor** release may break consumers and says
so under a **Breaking** heading; a **patch** does not. Each entry links the release commit, whose body carries the full
reasoning; the [dev log](https://tetherphp.com/devlog) tells the longer stories.

## [Unreleased]

## [0.12.0] — 2026-09-15

### Added
- A refused write is a result type. `make:resource` writes `Results\Invalid` — what was sent and what was wrong with
  it — and `Domains\<Feature>\Attributes`, the one class per feature where the fields and their rules live. `Store` and
  `Update` return `Written|Invalid`; their Responders answer a 303 for a `Written` and the form again, with the errors
  beside the fields, as a 422 for an `Invalid`. New stubs: `DomainWrite`, `ResultInvalid`, `Attributes`,
  `ResponderWrite`, `ResponderForm`. No new placeholders.

### Changed
- Every generated view is a whole page: it includes the skeleton's `partials/header.php` and `partials/footer.php` and
  sets `$pageTitle`. Generated views were bare fragments before.
- The form view shows `$errors` beside each field; `Create` and `Edit` render it with none.

## [0.11.3] — 2026-09-15

### Fixed
- `make:command db:schema` wrote a class called `Db:schemaCommand`, which PHP cannot parse. A colon now namespaces the
  command the way the framework's own are: `db:schema` is `DbSchemaCommand`, invoked as `db:schema`. A name with any
  other character that cannot appear in a class name is refused. The derivation table has tests for the first time.

## [0.11.2] — 2026-09-15

### Changed
- Every generated view opens with a `@var` docblock naming the variables its Responder passes, so an IDE no longer flags
  them as undefined. `StubsTest` reads each Responder stub's keys and asserts the matching view declares every one.

## [0.11.1] — 2026-09-15

### Fixed
- `tether context` described a layout three releases old: Results under `app/Domains/Results`, a feature as a flat
  `Actions\Blog`, `make:action <name>`, no `make:resource`, and "URIs are matched lowercased". Every entry now matches
  v0.10.0's layout and behaviour, and the test pins the ones that drifted.

## [0.11.0] — 2026-09-15

### Added
- `Interfaces\ServicesInterface`: what an application is made of, as one object. It declares the two properties the
  Kernel runs on — `public Env $env` and `public Log $log` — and nothing else.
- Every Action is constructed with `($request, $services)`. The Action hands its Domain the pieces the Domain asks for;
  the framework never looks inside the object past `env` and `log` and never names an application class.
- The Action stub takes `Services $services` beside the Request.
- `inspect` recognises a services class and lists what it provides; `context` carries a `services` entry. Both find the
  class by reflecting the routed Actions' constructors and never construct it.

### Breaking
- `Kernel::__construct(Router $router, ServicesInterface $services, array $middleware = [])` replaces
  `(Router, Env, Log, array)`. An application declares a class implementing `ServicesInterface`, builds it in
  `public/index.php`, and passes it to the Kernel.

## [0.10.3] — 2026-09-11

No source changed. Retires `docs/ROADMAP.md`, moving the decisions that still shape the code into the guides, and records
why sessions are not being extracted into their own package. `releasing.md` gains the lesson from 0.10.1: a patch can
still break something.

## [0.10.2] — 2026-09-10

### Fixed
- Restores the `readonly` on `Response`'s properties, which 0.10.1 silently dropped because an unrelated edit was in the
  working tree when the release was staged. 0.10.1 stays published with the weaker declaration; a tag is never moved.

## [0.10.1] — 2026-09-10

### Fixed
- `explain`, `inspect` and `context` read a union return type from `Domain::handle()` (`Post|PostNotFound`) instead of
  reporting the Result missing. A union is reported pipe-separated, `exists` is true only if every member exists, and
  `file` is null because it would name several.

## [0.10.0] — 2026-09-10

### Added
- `Request::$query` and `Request::$payload` beside `$params`: all three sources of input, each a plain public array. The
  body is parsed for every verb — JSON, then `$_POST` where PHP filled it, then a form-encoded body from the stream — so
  a form-encoded `PUT`, `PATCH` or `DELETE` no longer arrives empty.
- `Middleware\OverridesMethod`: reads `_method` from the body of a `POST` so a browser form can reach `PUT`, `PATCH` and
  `DELETE`. Opt-in, because it is the one behaviour triggered by a magic field name.
- `make:resource <name> [--uri]`: writes the seven ADR triples of a CRUD resource and prints the route lines for
  `routes/web.php` without editing it.
- Every feature is a directory. `make:feature` and `make:resource` share one set of stubs; `make:action`, `make:domain`
  and `make:responder` take `<feature> <operation>`, defaulting to `Index`.
- Results are named for their shape and shared: `Collection`, `Record`, `Written`, `Page`.
- The introspection commands read the Result off `Domain::handle()`'s declared return type and mark each part of a
  triple as declared or by convention.

### Changed
- `Router::match()` compares case-insensitively instead of `Request` lowercasing the URI, so a captured parameter keeps
  the case it was sent with. Slugs and UUIDs survive.
- `VerifyCsrfToken` reads the token off the request rather than `$_POST`, so the hidden field authorises every verb a
  form can ask for.
- The Request is built before middleware runs, so a middleware can read the body.

### Breaking
- `make:feature` writes `Actions\<Feature>\Index` into a directory, not `Actions\<Feature>` flat; the piecemeal
  generators take two arguments. Results moved under `Domains\<Feature>\Results\`.

## [0.9.0] — 2026-09-09

### Added
- `Interfaces\MiddlewareInterface` — one method, `__invoke(Request $request, \Closure $next): Response`. The list is
  given to the Kernel; nothing is discovered, and the Kernel turns an `HttpException` into a Response inside the
  middleware so headers added on the way out reach error pages.
- `Middleware\VerifyCsrfToken`: CSRF validation moved out of `Request` onto the seam, so an application that leaves it
  out boots with no session at all.
- `routes/middleware.php` is where an application declares the list; `routes`, `explain` and `context` load it and report
  what wraps a request.
- `Session::hasStarted()`.

### Changed
- Sessions start on first use rather than in the constructor. Building a middleware must have no side effects — the
  console constructs the list to read the class names off it — and this is what made that safe.

### Breaking
- `Kernel::__construct` takes `array $middleware` as its fourth argument; the Kernel no longer constructs a `Session` or
  a `CsrfToken`, and `Request` no longer validates a token.

## [0.8.0] — 2026-09-09

### Added
- The console: `routes`, `explain <uri>`, `inspect <class>`, `context`, `serve` and `test`, plus `make:action`,
  `make:domain` and `make:responder` on the same writer as `make:feature`.
- `Modules\Input` parses arguments and options; commands declare `$arguments` and `$options`, and both appear in
  `tether help <command>`.
- `Router::match()` resolves a URI without a `Request`, so the console can do it from a terminal.

### Changed
- `Env` is an immutable value built by `Env::fromFile()`; a missing key returns a default. `Log` takes its directory.
  Both are handed to the Kernel by `public/index.php` rather than found.
- The global functions are a closed list of ten, documented with a reason each.

### Removed
- `Env::getInstance()`, `Env::getEnv()`, static `Log` methods, the global `view()`, and the Kernel's hand-maintained
  `VERSION` constants (`context` asks Composer instead).

### Breaking
- `Kernel::__construct(Router, Env, Log)`; `Console::executeCommand()` and `Command::__construct` take an `Input`, and
  `$args`/`$opts` are gone.

## [0.7.0] — 2026-09-08

### Added
- `bin/tether`, declared as a Composer `bin`, so an application's `tether` file is a shim over `vendor/bin/tether`
  rather than its own copy of the console bootstrap.
- `Exceptions\HttpException` and the status subclasses: throwing one is how a request ends early, from an Action or a
  middleware, and the Kernel turns it into the error page.

### Breaking
- Requests end by throwing `HttpException`. Consumers need `^0.7` before `vendor/bin/tether` exists.

## [0.6.0] — 2026-09-07

### Added
- `Interfaces\DomainResult`: an empty marker giving `Domain::handle()` and `Action::respond()` a type. A Domain returns
  a `final readonly` value object; the Responder translates it into view variables and is the only place they are named.
- `make:feature` writes the Result before the Domain whose return type names it.

### Breaking
- `Domain::handle()` returns a `DomainResult`, not an array; a Responder's `__invoke()` takes its feature's result type.

## [0.5.0] — 2026-09-04

### Changed
- Requires PHP 8.5 (was 8.4). A raised platform floor is a breaking change under the pre-1.0 rule.
- `Kernel::restoreErrorHandlers()` takes the Kernel's own handlers back off, using 8.5's `get_error_handler()` and
  `get_exception_handler()` so it cannot clobber a handler installed after it. Every construction used to leak a pair.

## [0.4.0] — 2026-09-04

### Added
- `Http\Response`: an immutable value of body, status and headers. `send()` is the only place the framework writes to
  the client. `Kernel::run()` returns one on every path, which is what made the Kernel testable for the first time.
- `Routing\Route` says whether it matched, replacing `RouteDTO`.
- `Router::put()`, `patch()` and `delete()`; nested `group()`.
- Route parameters arrive as `$request->params`. The router had captured them all along and the Kernel dropped them.

### Changed
- `ActionInterface` is enforced: the Kernel checks the instance rather than `is_callable`.

### Breaking
- Actions return `Response` instead of `string`; `Responder::view()` and `json()` return `Response`; `ActionInterface`
  is required; `RouteDTO` is gone.

## [0.3.4] — 2026-09-04

Everything below was found by reading the code, not by the suite or PHPStan, and each was reproduced before being fixed.

### Fixed
- Every `HEAD` and `OPTIONS` request returned a 500: only `GET` and `POST` could be registered, so any other verb indexed
  a missing key. `HEAD` is answered from the `GET` table; unknown verbs get an empty one.
- Any URL with a query string returned a 404, because the raw `REQUEST_URI` was routed on.
- Dynamic matching kept the last match, so a later route silently shadowed an earlier one.
- CSRF could never pass on `PUT`, `PATCH` or `DELETE`: the token was read only from `$_POST`. `X-CSRF-Token` is read too.
- A rejected write was reported as a 500; it is a 403.
- Sessions start with `HttpOnly` and `SameSite=Lax`, `Secure` over TLS; `Session::regenerateId()` exists.
- The Responder stub's only `return` was commented out, so every generated feature fataled on first request.
- `boilerplate:clear` protected `'Action.txt'`, which cannot match a `.php` glob, and so deleted the `Action` base
  class; its `**` glob was not recursive. It recurses, protects the real base classes and asks first.
- `Log` lost writes silently on an unwritable directory; `Env` warned on lines with no `=`.

## [0.3.3] — 2026-09-02

### Changed
- PHPStan level 8 throughout. Thirteen unchecked failure paths — `glob()`, `file_get_contents()` and `preg_replace()`
  returning `false`/`null`, a route pointing at a class with no `__invoke()`, arithmetic on untrusted session values —
  now fail usefully instead of silently or fatally. `Command::execute()` is declared on the base class.

## [0.3.2] — 2026-09-02

### Fixed
- A `POST` with no `csrf_token` field, or a session that never had a token, produced a 500 instead of a rejection.
- Command registration reports why a class was skipped instead of dropping it silently.
- `declare(strict_types=1)` in every source file; CI runs tests and analysis on push; a fixture application under
  `tests/Fixtures` makes anything reading `app_dir()` or `views_dir()` testable.

## [0.3.1] — 2026-09-02

Documentation only: a published tag must never be moved, learned from 0.3.0.

## [0.3.0] — 2026-09-02 — do not use

The tag was placed on the wrong commit and force-moved. Packagist had already read it, so the published 0.3.0 is
permanently the pre-refactor tree and does not contain the fix its message describes (every `GET` rendering the 500
view with a 200 status). Constrain to `^0.3.1` or later.

## 0.1.4 – 0.2.3 — 2025-08-21 to 2026-03-09

Generated by a `splitsh-lite` split of `src/` out of the skeleton repository, force-pushed on every merge. No tests,
CI or history survived in this repository. Not recommended; the framework became a standalone package at 0.3.0.

[Unreleased]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.12.0...HEAD
[0.12.0]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.11.3...v0.12.0
[0.11.3]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.11.2...v0.11.3
[0.11.2]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.11.1...v0.11.2
[0.11.1]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.11.0...v0.11.1
[0.11.0]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.10.3...v0.11.0
[0.10.3]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.10.2...v0.10.3
[0.10.2]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.10.1...v0.10.2
[0.10.1]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.10.0...v0.10.1
[0.10.0]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.9.0...v0.10.0
[0.9.0]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.8.0...v0.9.0
[0.8.0]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.7.0...v0.8.0
[0.7.0]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.3.4...v0.4.0
[0.3.4]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.3.3...v0.3.4
[0.3.3]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.3.2...v0.3.3
[0.3.2]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.3.1...v0.3.2
[0.3.1]: https://github.com/Dillonsmart/tetherphp-core/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/Dillonsmart/tetherphp-core/releases/tag/v0.3.0
