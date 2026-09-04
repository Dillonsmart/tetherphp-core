# TetherPHP Core

The core framework package behind [TetherPHP](https://github.com/Dillonsmart/tetherphp) — routing, request handling,
sessions, CSRF protection, the console kernel and the code-generation stubs.

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
├── Kernel.php          # Boots the app: env, session, CSRF, error handling, dispatch
├── Router.php          # Route registration, groups, static + dynamic matching
└── framework/
    ├── Commands/       # Built-in console commands (make:*, help, boilerplate:clear)
    ├── DTOs/           # RouteDTO
    ├── Helpers/        # Global functions and the Route view helper
    ├── Interfaces/     # ActionInterface, RequestInterface, ResponderInterface
    ├── Modules/        # Console, Env, Log
    ├── Requests/       # Request
    ├── Sessions/       # Session, CsrfToken
    ├── Stubs/          # Templates used by the make:* commands
    ├── Traits/         # Strings
    └── Views/          # Fallback error views
tests/
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
