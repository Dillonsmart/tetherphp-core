# TetherPHP Core

The core framework package behind [TetherPHP](https://github.com/Dillonsmart/tetherphp) — routing, request handling,
sessions, CSRF protection as composable middleware, the console kernel and the code-generation stubs.

This repository is the **source of truth** for the framework. The `dillonsmart/tetherphp` skeleton application consumes
it as a Composer dependency; framework changes are made here, not there.

## Requirements

- PHP 8.5 or higher (property hooks and `new` without parentheses are used throughout)
- Composer 2

## Installation

```bash
composer require dillonsmart/tetherphp-core
```

Most people get it transitively by starting from the skeleton application:

```bash
composer create-project dillonsmart/tetherphp ./
```

## Layout

```
src/
├── Kernel.php          # Boots the app: error handling, middleware, dispatch
├── Router.php          # Route registration, groups, static + dynamic matching
└── framework/
    ├── Commands/       # Built-in console commands (make:*, routes, explain, inspect, context, serve, test)
    │                   #   make:resource writes a whole CRUD resource
    ├── Exceptions/     # HttpException and the statuses that subclass it
    ├── Helpers/        # Global functions and the Route view helper
    ├── Http/           # Response
    ├── Interfaces/     # ActionInterface, DomainResult, MiddlewareInterface, RequestInterface, ResponderInterface
    ├── Middleware/     # VerifyCsrfToken, OverridesMethod
    ├── Modules/        # Console, Input, Env, Log
    ├── Requests/       # Request
    ├── Routing/        # Route (the result of matching)
    ├── Sessions/       # Session, CsrfToken
    ├── Stubs/          # Templates used by the make:* commands (one set for features and resources)
    ├── Traits/         # Strings, GeneratesFiles, GeneratesTriples, InspectsApplication
    └── Views/          # Fallback error views
tests/
├── Feature/            # needs the fixture application
├── Fixtures/           # the application and routes linked into place by the bootstrap
└── Unit/
```

## Path helpers

`src/framework/Helpers/GlobalFunctions.php` is autoloaded via Composer's `files` and defines the path helpers the
framework relies on. Two of them are easy to confuse:

| Helper           | Resolves to                                          |
| ---------------- | ---------------------------------------------------- |
| `project_root()` | The **consuming application** root, via `InstalledVersions` |
| `package_root()` | This package's own root                              |
| `core_dir()`     | This package's `src/framework`, resolved from the helper file |

Anything that reads framework-shipped assets (stubs, fallback error views) must go through `core_dir()` / `core_views()`.
Anything that reads application files (`app/`, `storage/`, `public/`) must go through `project_root()`. Resolving a
framework asset from `project_root()` breaks as soon as the package is installed under `vendor/`.

## Testing

```bash
composer install
composer test
```

## Local development against the skeleton app

See `docs/agents/linked-core-development.md` in this repository for the linked-checkout workflow, or the
"Local Development" section of the skeleton application's README.

## Releasing

Tag this repository; Packagist picks the tag up. The skeleton application then bumps its constraint on
`dillonsmart/tetherphp-core`. Note that a breaking change to the autoload layout or to the path helpers requires a
matching release of the skeleton application.

## Contributing

Contributions are welcome — please open an issue or pull request.

## License

Open-sourced under the MIT license. See [LICENSE.md](LICENSE.md).
