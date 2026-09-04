# Working on the framework

> Read this before changing anything under `src/` — routing, requests, sessions, console commands, stubs, path helpers or error views — or when deciding whether a change belongs in the framework or in the skeleton application.

This repository is the **source of truth** for the TetherPHP framework. It is published to Packagist as
`dillonsmart/tetherphp-core` and consumed by the `dillonsmart/tetherphp` skeleton application.

It is not generated from anything. (It used to be produced by splitting the skeleton's `src/` with `splitsh-lite`,
which force-pushed over this repository's history. That workflow is gone — never reintroduce it.)

## Design constraints

Every change here answers to the six core principles — see the principles guide (`docs/agents/principles.md`). The two that most often
decide a framework change: **Small & Composable** (could this be a package instead of core?) and **One Obvious Way**
(does this add a second way to do something that already works?).

## Layout

```
src/
├── Kernel.php          # boot: env, session, CSRF, error handlers, dispatch
├── Router.php          # registration, groups, static + dynamic matching
└── framework/
    ├── Commands/       # built-in console commands
    ├── Http/           # Response
    ├── Routing/        # Route
    ├── Helpers/        # GlobalFunctions.php (Composer `files`), Route
    ├── Interfaces/     # ActionInterface, RequestInterface, ResponderInterface
    ├── Modules/        # Console, Env, Log
    ├── Requests/       # Request
    ├── Sessions/       # Session, CsrfToken
    ├── Stubs/          # *.txt templates for the make:* commands
    ├── Traits/         # Strings
    └── Views/errors/   # fallback error views
tests/Unit/
```

`TetherPHP\` is PSR-4 mapped to `src/`. A file at `src/framework/Modules/Log.php` is
`TetherPHP\framework\Modules\Log` — note the lowercase `framework` segment, which is deliberate and load-bearing.

## The path-helper rule

This is the easiest thing to get wrong and it fails only once the package is installed under `vendor/`, never in a
linked local checkout. `src/framework/Helpers/GlobalFunctions.php` defines:

| Helper                                             | Points at                          | Use for                                |
| -------------------------------------------------- | ---------------------------------- | -------------------------------------- |
| `project_root()`                                    | the **consuming application** root | `app/`, `storage/`, `public/`, `.env`  |
| `package_root()`                                    | this package's root                | this package's own metadata            |
| `core_dir()` / `core_views()`                       | this package's `src/framework`     | stubs, fallback error views            |

**Never read a framework-shipped asset through `project_root()`, and never read an application file through
`core_dir()`.** `core_dir()` is resolved as `dirname(__DIR__)` from the helper file rather than from `package_root()`
precisely so it survives installation into `vendor/dillonsmart/tetherphp-core`. If you change how these resolve,
`tests/Unit/GlobalFunctionsTest.php` is the regression guard.

`project_root()` uses `Composer\InstalledVersions::getRootPackage()`, so it only works when the autoloader is loaded —
it cannot be called before `vendor/autoload.php`.

## PHP version

Every file under `src/` declares `strict_types=1`. That is what turns a silently coerced argument into an error, so
do not omit it from a new file — and be aware it is why `logger($e, 'error')` had to become
`logger($e->getMessage(), 'error')`: an `Exception` was being coerced to a string via `__toString()`, dragging a
stack trace into the log.

`>=8.4`, and the code genuinely depends on it:

- **Property hooks** — `Request::$method` and `$uri` normalise via `set` hooks (`$uri` is lowercased, so route
  matching is case-insensitive and captured dynamic params arrive lowercased).
- **Interface property declarations** — `RequestInterface` declares `public string $method {set; get;}`.
- **`new` without parentheses** — e.g. `new Console($command)->executeCommand(...)`.

Do not "simplify" these into pre-8.4 forms.

## Adding a console command

1. Add the class in `src/framework/Commands/`, extending `Command`, setting `$command` and `$description`.
2. `Console::registerCommands()` globs `src/framework/Commands/*.php` and maps them to
   `TetherPHP\framework\Commands\<Basename>` — the class name must match the filename or it is silently skipped.
3. Registration requires `class_exists()` **and** `is_subclass_of(..., Command::class)`. A command failing either
   check is **skipped with a reason** written to stderr and recorded in `Console::skipped()` — it used to disappear
   silently, which made a misnamed class, a missing psr-4 mapping and a file that was never written
   indistinguishable. Duplicate command names and a missing `$command` are reported the same way.
4. `Console` takes an optional error stream as its second argument, so tests can capture diagnostics instead of
   letting them reach stderr.

Applications get their own commands from `app/Commands/` under the `Commands\` namespace; that mapping lives in the
skeleton's `composer.json`.

## Stubs

`make:*` commands read `core_dir() . '/Stubs/*.txt'` and substitute placeholders. The supported set is:

| Placeholder       | Substituted by                | Used in       |
| ----------------- | ----------------------------- | ------------- |
| `{{className}}`   | all `make:*` commands         | every stub    |
| `{{commandName}}` | `MakeCommand`                 | `Command.txt` |
| `{{viewName}}`    | `MakeFeatureCommand`          | `Responder.txt`, `View.txt` |

A placeholder no generator substitutes is emitted literally into the developer's file, so the set is a contract —
`tests/Unit/StubsTest.php` enforces it. Adding one means updating the stub, the command that renders it, and that test.

`make:command` derives both names from one argument: `send-welcome-email`, `SendWelcomeEmail` and
`SendWelcomeEmailCommand` all produce class `SendWelcomeEmailCommand` and command name `send-welcome-email`. The
class name is `toValidClassName()` with any redundant `Command` suffix stripped; the command name is
`toKebabCase()` of that. `Command.txt` used to hardcode `tetherphp:command`, so every generated command collided in
the registry — do not reintroduce a literal command name into a stub.

## The pipeline

`Kernel::run()` returns a `Response`. Every path through it does — a match, a
miss, a rejected write, a misconfigured route. Nothing is echoed and nothing
calls `exit()`, which is what makes the Kernel testable at all; it had no
coverage until this changed.

```
Request → Route → Action → Domain → Responder → Response
```

- **`Response`** (`framework/Http`) is an immutable value: body, status, headers.
  `send()` is the only place the framework writes to the client.
- **`Route`** (`framework/Routing`) says whether it matched. It replaced
  `RouteDTO`, which signalled "not found" by leaving a typed property
  uninitialised for callers to test with `isset()`.
- **Actions must implement `ActionInterface`** and return a `Response`. The
  Kernel checks the instance and 500s with a log line if it does not; before,
  only `is_callable` was checked.
- **Route parameters arrive on the request** as `$request->params`. The router
  always captured them and the Kernel dropped them, so applications re-parsed
  the URI inside their own Actions.

## Request handling invariants

- **Route on the path, not `REQUEST_URI`.** The Kernel strips the query string with `parse_url`; routing on the raw
  value meant any URL carrying parameters 404'd.
- **Never index `$routes[$method]` directly.** Only GET and POST can be registered, so any other verb used to hit a
  missing key and take the request down with a TypeError. `Router::routesFor()` is the only accessor; it defaults to
  an empty table and answers HEAD from the GET one.
- **Error pages go through `Kernel::errorResponse()`**, which prefers the application's `app/Views/errors/{status}.php`
  and falls back to the framework's own. It returns the body rather than echoing it, so `run()` returns what it says
  it returns.
- **A rejected write is a 403, not a 500.** CSRF failures are caught in `run()`.
- **The CSRF token is read from `$_POST` or the `X-CSRF-Token` header.** PHP only populates `$_POST` for POST
  bodies, so header support is what makes PUT, PATCH and DELETE authorisable at all.

## Tests

```bash
composer install
composer test
```

```bash
composer check          # tests + static analysis, what CI runs
```

PHPUnit 11, bootstrapped from `tests/bootstrap.php`. Two suites:

- `tests/Unit` — no application required.
- `tests/Feature` — exercises code that reads `app_dir()`, `views_dir()` or `.env`.

`project_root()` in this repository *is* this repository, so anything touching an application needs one to exist at
that root. `tests/bootstrap.php` symlinks `tests/Fixtures/app` to `./app` and writes a `.env` if absent; both are
gitignored. Add fixtures there rather than mocking the path helpers.

PHPStan runs at **level 8** over `src` and `tests` (`composer analyse`). Fixtures are excluded deliberately — they
contain classes that are wrong on purpose.

Level 9 is not yet reachable, and the reason is structural rather than cosmetic: `Session::get()` returns `mixed`,
and `$_SERVER` and `$_POST` enter the framework untyped, so every value derived from them is `mixed` too. Fixing
that means typing the request/session boundary, which is Phase 2/3 work — do not close the gap with casts or
`@phpstan-ignore`.

Cover behaviour that consumers depend on: route resolution order (static wins over dynamic), group prefixing, the
path helpers, CSRF acceptance and rejection, and the stub placeholder contract.

## Where a change belongs

| Change                                                          | Repository            |
| --------------------------------------------------------------- | --------------------- |
| Routing, request, session, CSRF, logging, env, console, stubs     | here                  |
| Fallback error views (`framework/Views/errors/`)                  | here                  |
| Application error views, pages, partials, routes, assets          | skeleton              |
| Anything referencing `Actions\`, `Domains\`, `Responders\`        | skeleton — the framework must not depend on application namespaces |

That last row matters: the framework is a library and must not `use` an application namespace.

## Keeping this guide current

These guides are part of the source, not documentation about it. When a change makes anything above inaccurate —
a moved directory, a new or renamed path helper, a changed PHP requirement, a new stub placeholder, a change to how
commands register — update this file in the same commit. The same applies to `docs/agents/releasing.md`
if the release flow changes, and to `README.md`.
