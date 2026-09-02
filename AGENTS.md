# tetherphp-core

The TetherPHP framework package. This repository is the **source of truth** for framework code; the
`dillonsmart/tetherphp` skeleton application consumes it as a Composer dependency from Packagist.

It is not generated from anything. It used to be produced by splitting the skeleton's `src/` with `splitsh-lite`,
which force-pushed over this repository's history. That workflow is gone — do not reintroduce it.

## The six core principles

Every design decision answers to these. The **tetherphp-principles** skill carries the full charter and a review
checklist; the short form:

1. **Human First** — code should be obvious to a human. No cleverness for cleverness' sake.
2. **Agent Ready** — everything a human can understand, an agent should be able to understand. Predictable naming,
   explicit dependencies, consistent structure, machine-readable context.
3. **Explicit Over Magic** — `Request → Route → Action → Domain → Responder → Response` must be traceable without
   knowing implicit framework behaviour.
4. **One Obvious Way** — opinionated, one clear convention rather than five approaches. Adding a second way to do
   something means removing the first.
5. **Small & Composable** — the core does less, but does it well. Extra functionality composes in as packages.
6. **Tools Are Part of the Framework** — the CLI is first-class. A runtime feature is not finished until the tooling
   can show it.

## Setup

```bash
composer install
composer test          # PHPUnit 11, suite in tests/Unit
```

Requires **PHP >= 8.4**. Property hooks, interface property declarations and parenthesis-free `new` are used
throughout and are load-bearing — do not rewrite them into pre-8.4 forms.

## Skills

Detailed working knowledge lives in `.claude/skills/`. Read the relevant one before making changes:

- **core-framework** — layout, the `project_root()` / `package_root()` / `core_dir()` rule, PHP 8.4 features in use,
  adding console commands and stubs, what belongs here versus in the skeleton.
- **core-release** — tagging, pre-1.0 version semantics, and coordinating the skeleton's version constraint.

## The rule that catches people out

`core_dir()` and `core_views()` point at **this package's** files; `project_root()` points at the **consuming
application**. Never read a framework-shipped asset (stubs, fallback error views) through `project_root()`, and never
read an application file through `core_dir()`. Getting this wrong still works in a linked local checkout and only
fails once the package is installed under `vendor/` — `tests/Unit/GlobalFunctionsTest.php` is the regression guard.

## Where a change belongs

| Change                                                       | Repository       |
| ------------------------------------------------------------ | ---------------- |
| Routing, request, session, CSRF, logging, env, console, stubs  | here             |
| Fallback error views (`src/framework/Views/errors/`)           | here             |
| Actions, Domains, Responders, views, routes, assets            | `tetherphp`      |

The framework is a library and must not `use` an application namespace (`Actions\`, `Domains\`, `Responders\`).

## Testing conventions

The suite must stay runnable with **no consuming application present** — in this repository `project_root()` is this
repository, so tests must not depend on `app_dir()`, `views_dir()` or a `.env`. Anything needing a real application
belongs in an integration test against a linked skeleton checkout.

Cover behaviour consumers depend on: route resolution order, group prefixing, the path helpers, and the stub
placeholder contract.

## Keeping documentation current

The skills in `.claude/skills/` are part of the source, not documentation about it. When a change makes one of them
inaccurate — a moved directory, a renamed helper, a new stub placeholder, a changed release step — update the skill
in the **same commit** as the change, along with this file and `README.md` where they are affected. A skill that has
drifted is worse than no skill.
