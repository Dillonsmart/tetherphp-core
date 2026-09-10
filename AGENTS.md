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

The refactor sequence that brings the code into line with them is in [docs/ROADMAP.md](docs/ROADMAP.md). Check it
before starting framework work — a change that belongs to a later phase may be waiting on an earlier one.

## Setup

```bash
composer install
composer test          # PHPUnit 11, suite in tests/Unit
```

Requires **PHP >= 8.5**. Property hooks, interface property declarations and parenthesis-free `new` are used
throughout and are load-bearing — do not rewrite them into pre-8.4 forms.

## Guides

Detailed working knowledge lives in `docs/agents/`. These are plain markdown and tool-agnostic — read the relevant
one before making changes:

- [`docs/agents/principles.md`](docs/agents/principles.md) — the six principles in full, with a review checklist.
- [`docs/agents/framework.md`](docs/agents/framework.md) — layout, the path-helper rule, PHP version features in use,
  adding console commands, the `bin/tether` binary, and stubs; what belongs here versus in the skeleton.
- [`docs/agents/releasing.md`](docs/agents/releasing.md) — tagging, pre-1.0 version semantics, and coordinating the
  skeleton's version constraint.

## The rule that catches people out

`core_dir()` and `core_views()` point at **this package's** files; `project_root()` points at the **consuming
application**. Never read a framework-shipped asset (stubs, fallback error views) through `project_root()`, and never
read an application file through `core_dir()`. Getting this wrong still works in a linked local checkout and only
fails once the package is installed under `vendor/` — `tests/Unit/GlobalFunctionsTest.php` is the regression guard.

## What the Kernel is given

`Kernel::__construct(Router $router, Env $env, Log $log)`. The environment file and the log directory are chosen by
the application in `public/index.php`, not found by the framework — `Env::getInstance()` and the static `Log` are
gone as of `v0.8.0`.

The Kernel installs both for the `env()` and `logger()` helpers, which are one-line delegates and the **only**
callers of `Env::current()` / `Log::current()`. A framework class reaching for `current()` instead of taking the
object through its constructor is a review failure. The global functions are a closed list of ten, documented with a
reason each in [`docs/agents/framework.md`](docs/agents/framework.md#the-global-functions-are-a-closed-list).

## Middleware is how anything composes in

`Kernel::__construct(Router $router, Env $env, Log $log, array $middleware = [])`. A middleware is one method —
`__invoke(Request $request, \Closure $next): Response` — and the list is given, never discovered, so the order things
run in is the order it is written in the application's `routes/middleware.php`.

**Building a middleware must have no side effects.** `tether routes`, `explain` and `context` build an application's
list to report what runs around a request, so a constructor that opens a connection or starts a session does it from
a terminal too. `Session` starts lazily for exactly this reason.

The framework starts **no session**, checks **no CSRF token** and honours **no `_method` field** on its own. All
three used to be welded into the Kernel constructor or into `Request::__construct()`; an application composes
`Middleware\VerifyCsrfToken` and `Middleware\OverridesMethod` in if it wants them. This is the seam Principle 5 depends on — before it existed there
was nowhere for a package to attach, so "extra functionality composes in as packages" was not true of anything.

See [`docs/agents/framework.md`](docs/agents/framework.md#middleware-the-composition-seam) for the two design
decisions behind it and the known tooling gap.

## A request carries three kinds of input

`$request->params` from the path, `$request->query` from the query string, `$request->payload` from the body. Each
is a plain public array, read with `??`:

```php
$id    = $request->params['id'] ?? '';
$page  = $request->query['page'] ?? '1';
$title = $request->payload['title'] ?? '';
```

There is deliberately **no `input()` accessor** over the top of them: it would be a second way to read the same
value and would hide which of the three it came from. Adding one is a review failure.

Two rules the Kernel keeps so those arrays can be trusted:

- **All three are populated before any middleware runs.** `payload` used to be assigned during dispatch, after every
  middleware had been and gone.
- **Nothing normalises the URI.** Matching is case-insensitive because `Router::match()` compares that way, not
  because `Request` lowercases anything. It used to, and every parameter captured out of a lowercased URI was
  lowercased too — so a slug or a UUID could not be routed. Do not put normalisation back on `Request`.

## Every feature is a directory

```
app/Actions/Blog/Index.php              Actions\Blog\Index
app/Domains/Blog/Index.php              Domains\Blog\Index
app/Domains/Blog/Results/Page.php       Domains\Blog\Results\Page
app/Responders/Blog/Index.php           Responders\Blog\Index
app/Views/pages/blog/index.php
```

`make:feature` and `make:resource` write the same layout and share the same stubs; the resource just writes seven
operations into the directory instead of one. **A feature grows by addition** — `make:action Blog Show` puts a
second operation beside the first and touches nothing.

Features used to be flat (`app/Actions/Blog.php`) while resources nested, which was two layouts for one concept and
made the second route a feature ever needed cost four moved files and four rewritten namespaces. The views were
always nested; only the classes disagreed.

A Result lives with its Domain, under the feature, so one feature owns one directory under `Domains/`. Actions,
Domains and Responders are named for the operation; **a Result is named for its shape and shared** by every
operation that answers the same way — `Collection` for many, `Record` for one, `Written` for a write that redirects,
`Page` for a page with neither behind it. Naming one per operation produced classes differing from each other by
their class name and nothing else.

Because they are shared, **`triple()` reads the Result off `Domain::handle()`'s return type** rather than predicting
it from the Action's name — there is no `Results\Show` to predict. Reflection reads a signature and constructs
nothing, so the rule that introspection never instantiates an Action, Domain or Responder still holds. Each part of
a triple carries a `declared` flag saying whether the code stated it or the command guessed.

`Traits\GeneratesTriples` holds the layout, the seven-operation table and the writers. All five `make:*` triple
commands are thin shells over it — read the trait before changing any of them. Which shape you get is predictable
from the command: `make:resource` writes the CRUD shapes, every other generator writes the page shape.

## Full CRUD is Phase 7

`tether make:resource <Name>` writes the seven ADR triples of a resource and prints the seven routes for
`routes/web.php` — it never edits the route table itself, and there is no `$router->resource()`. A browser form
reaches PUT, PATCH and DELETE through `Middleware\OverridesMethod`, which is opt-in for the reason above: it is the
one thing in the framework triggered by a magic field name, so it lives where a reader and `tether routes` can both
see it.

## Where a change belongs

| Change                                                       | Repository       |
| ------------------------------------------------------------ | ---------------- |
| Routing, request, session, CSRF, logging, env, console, stubs  | here             |
| The console binary (`bin/tether`) and how it boots             | here             |
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

The guides in `docs/agents/` are part of the source, not documentation about it. When a change makes one of them
inaccurate — a moved directory, a renamed helper, a new stub placeholder, a changed release step — update the guide
in the **same commit** as the change, along with this file and `README.md` where they are affected. A skill that has
drifted is worse than no skill.
