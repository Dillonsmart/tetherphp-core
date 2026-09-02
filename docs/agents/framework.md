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
    ├── DTOs/           # RouteDTO
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
3. Registration requires `class_exists()` **and** `is_subclass_of(..., Command::class)`. A command that fails either
   check disappears from `tether help` with no error.

Applications get their own commands from `app/Commands/` under the `Commands\` namespace; that mapping lives in the
skeleton's `composer.json`.

## Stubs

`make:*` commands read `core_dir() . '/Stubs/*.txt'` and substitute placeholders. The supported set is:

| Placeholder       | Substituted by                | Used in       |
| ----------------- | ----------------------------- | ------------- |
| `{{className}}`   | all `make:*` commands         | every stub    |
| `{{commandName}}` | `MakeCommand`                 | `Command.txt` |

A placeholder no generator substitutes is emitted literally into the developer's file, so the set is a contract —
`tests/Unit/StubsTest.php` enforces it. Adding one means updating the stub, the command that renders it, and that test.

`make:command` derives both names from one argument: `send-welcome-email`, `SendWelcomeEmail` and
`SendWelcomeEmailCommand` all produce class `SendWelcomeEmailCommand` and command name `send-welcome-email`. The
class name is `toValidClassName()` with any redundant `Command` suffix stripped; the command name is
`toKebabCase()` of that. `Command.txt` used to hardcode `tetherphp:command`, so every generated command collided in
the registry — do not reintroduce a literal command name into a stub.

## Tests

```bash
composer install
composer test
```

PHPUnit 11, bootstrapped from `tests/bootstrap.php`, suite in `tests/Unit`. The suite must stay runnable with no
consuming application present — so do not write tests that depend on `app_dir()`, `views_dir()` or a `.env`, because
in this repository `project_root()` is this repository. Anything needing an app belongs in an integration test against
a linked skeleton checkout instead.

Cover behaviour that consumers depend on: route resolution order (static wins over dynamic), group prefixing, and the
path helpers.

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
