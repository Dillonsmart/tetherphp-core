# TetherPHP refactor roadmap

Taking the framework from working to compliant with its [six core principles](../AGENTS.md#the-six-core-principles).
Ordered by dependency: Phases 2 and 3 are both breaking and ship together so consumers migrate once.

Current: `v0.3.0`. Target: `v1.0`.

Items struck through below have landed since this roadmap was written.

## The spine

```
Request → Route → Action → Domain → Responder → Response
```

There is no `Response` object. `Kernel::run()` returns a string, or echoes a rendered view, or `include`s an error
page and calls `exit()` — three exits from one method. That is the largest obstacle to a traceable pipeline, and
Phase 2 is entirely about it.

## Audit

Read from the current source, not inferred. The principle named is the one the defect most offends.

| Finding | Why it matters | Offends |
| --- | --- | --- |
| `Kernel::run()` exits three ways | The response is emitted from three places, so it cannot be tested, wrapped or inspected | Explicit |
| ~~Error handler made every warning fatal~~ **fixed** | A deprecation notice replaced the page with a 500. Now only `E_ERROR`-class errors are fatal; the rest are logged | Human First |
| ~~`tether` discarded command exit codes~~ **fixed** | Every invocation exited `0`, so no script or CI job could detect a failure | Agent Ready |
| ~~`make:command` hardcoded the command name~~ **fixed** | Every generated command claimed `tetherphp:command` and collided in the registry | One Obvious Way |
| No `Response` object | The last stage of the stated pipeline has no representation in code | Explicit |
| ~~`$_SERVER['CONTENT_TYPE']` read unguarded~~ **fixed** | Warned on every GET. The error handler treated *any* warning as fatal, so every page served the 500 view — and because `exit(500)` sets a process code rather than an HTTP one, it did so with a `200` status | Human First |
| `Request::$csrfToken` is `string` | `Session::get()` returns null when no token exists — TypeError on a fresh session | Human First |
| `validateCsrfToken($_POST['csrf_token'])` | Undefined array key on a POST without the field, before validation can reject it | Human First |
| `RouteDTO` uses uninitialised properties | "Not found" is signalled by `isset($route->action)` — absence-as-control-flow | Agent Ready |
| `Router::group()` copies routes onto themselves | Dead loop that reads as though it does something | Human First |
| Two ways to render a page | `$router->view()` bypasses the ADR chain entirely | One Obvious Way |
| `env()` vs `Env::getInstance()` | Two public routes to the same state, one a singleton. Same for `logger()` vs `Log::error()` | One Obvious Way |
| `toPascalCase()` and `toValidClassName()` | Identical implementations under two names | One Obvious Way |
| Commands vanish silently | `class_exists()` and `is_subclass_of()` fail closed with no diagnostic | Agent Ready |
| `Command::argument()` binds by position | `array_search` over `$arguments` keys; reordering silently rebinds. `$opts` is never parsed | Agent Ready |
| No introspection commands | Nothing prints the route table, explains a URI, or emits machine-readable context | Tools |
| No `make:action` / `make:domain` / `make:responder` | `make:feature` generates all three or nothing | Tools |
| No `declare(strict_types=1)`, partial return types | `MakeFeatureCommand::execute(): int` is typed; `MakeCommand::execute()` is not | Human First |
| Session, CSRF and Log hardwired into `Kernel` | Concrete classes in the constructor. No seam, so nothing can be extracted | Composable |
| Interfaces bind nothing | `ActionInterface` is enforced nowhere | Explicit |
| No static analysis, no CI | Neither repository runs anything on push | Agent Ready |

## Phase 1 — Make it honest · `v0.3.x`, non-breaking

Nothing new. Stop the framework lying about what it does.

- ~~`declare(strict_types=1)` across `src/`~~ **done** (20 files), and ~~return/value types throughout~~ **done** —
  every method and array property now carries one, natively or in a docblock. `Command::execute()` is declared on
  the base class at last (Console had always called a method that was never declared) but deliberately without a
  *native* return type, since adding one breaks subclasses that override without it. That is a `v0.4.0` change.
- ~~Unguarded `CONTENT_TYPE`~~, ~~the error handler treating warnings as fatal~~, ~~the nullable CSRF token~~,
  ~~the missing `$_POST` key~~, ~~the no-op loop in `Router::group()`~~ — **all done**.
- ~~Command registration fails **loudly**~~ **done** — unloadable classes, non-subclasses, missing `$command` and
  duplicate names each report a reason.
- ~~PHPStan, plus GitHub Actions running tests and analysis on both repositories~~ **done** — **level 8**, clean;
  CI on all three repos. Getting there fixed thirteen unchecked failure paths: `glob()`, `file_get_contents()` and
  `fopen()` returning `false`, `preg_replace()` returning `null`, a route class that might not be invokable, and
  session values reaching arithmetic untyped.
- ~~An integration harness (a fixture application inside `tests/`)~~ **done** — `tests/Fixtures/app`, linked into
  place by the bootstrap, with a `tests/Feature` suite.

**Phase 1 is complete.** A follow-up review then found a further set of defects that neither the tests nor PHPStan
could see, all fixed in `v0.3.4`: every non-GET/POST request returned 500; any URL with a query string 404'd; the
skeleton's `Responder::view()` let view data rebind `$file` after its existence check and include an arbitrary path;
route parameters were captured and discarded; `make:feature` generated a Responder that fataled on first use;
`boilerplate:clear` deleted the base Action class and missed nested files; sessions had no cookie hardening; and
CSRF could never pass on PUT, PATCH or DELETE.

What Phase 1 could not reach, and why:

- **PHPStan level 9** — blocked structurally. `Session::get()` returns `mixed` and `$_SERVER`/`$_POST` enter
  untyped, so everything derived from them is `mixed`. Typing that boundary is Phase 2/3.
- **Kernel test coverage** — the Kernel still `exit()`s, so it cannot be tested until Phase 2 gives it one exit.
- **A native `int` return on `Command::execute()`** — breaking, so `v0.4.0`.

These three are the argument for Phase 2, not leftovers from Phase 1.

## Phase 2 — One explicit pipeline · `v0.4.0`, breaking

Give the last stage of the pipeline a body, and make the Kernel have exactly one exit.

- Introduce a `Response` value object — status, headers, body. `Kernel::run(): Response`, and `public/index.php` is
  the only thing that emits.
- Remove every `include` and `exit()` from `Kernel`. 404 and 500 become ordinary `Response` values, and testable.
- Enforce `ActionInterface`: `__invoke(): Response`.
- Replace `RouteDTO` with a `Route` that states whether it matched.
- Resolve the two rendering paths. Recommended: keep `$router->view()` as sugar resolving to a framework-supplied
  Action, so there is one path through the pipeline and one way to write a static page.

**Done when** every route — matched, unmatched or erroring — returns a `Response`, and the Request-to-Response path
can be followed by reading `Kernel::run()` top to bottom.

**Risk** Every existing Action's signature changes. The skeleton and the website update in lockstep with the release.

## Phase 3 — Explicit dependencies · `v0.4.0`, breaking

Remove the ambient state. What a thing needs should arrive through its constructor.

- Retire the `Env` singleton. Construct once in `Kernel` and pass it down; `getInstance()` goes.
- `Log` becomes an instance with a configured destination.
- Decide the global-function contract deliberately. They serve Human First and should not all die — but each survivor
  must be a thin delegate to an explicit object, and the list must be documented and closed.
- Collapse `toValidClassName()` into `toPascalCase()`.

**Done when** no framework class reaches for global state to do its job, and the surviving global functions are
enumerated in `AGENTS.md` with a stated reason for each.

**Ship with Phase 2** — one migration guide, one upgrade.

## Phase 4 — The CLI becomes the product · `v0.5.0`

Principle 6 taken literally: the framework should explain the application, not merely run it.

- A real argument and option parser. `Command::argument()` stops binding by position; `$opts` starts working.
- `make:action`, `make:domain`, `make:responder` to complement `make:feature`.
- `tether routes` — the resolved route table, static and dynamic.
- `tether explain <uri>` — the concrete path a URI takes through the pipeline. The pipeline principle made executable.
- `tether inspect <class>` — what a class is in ADR terms and what it depends on.
- `tether context` — machine-readable JSON map of the application for agents: routes, ADR triples, commands,
  conventions.
- `tether test` and `tether serve`.

**Done when** an agent handed only `tether context` output can correctly name where a new feature's files belong and
which existing route would conflict.

## Phase 5 — Seams and packages · `v0.6.0`

Prove that "small core, composed beyond" is a real architecture.

- Interfaces for the replaceable concerns — session storage, logging, view rendering — defined by what the Kernel
  actually needs, not speculatively.
- Extract one concern to prove the seam. Sessions and CSRF are the strongest candidate: self-contained, clearly
  optional for an API-only application, currently welded into the Kernel constructor.
- Document the package convention so the second extraction is mechanical.

**Done when** an application can boot without the session package installed, and the core has no reference to it.

**Watch** This phase can quietly become an abstraction exercise. Only extract a concern something real would replace;
an interface with one implementation and no prospect of a second is complexity charged against Human First.

## Phase 6 — Surface · `v1.0` track

- ~~Move the documentation website out of the skeleton into its own project.~~ **done** — it now lives in the
  private `tetherphp-website` repository, itself built on TetherPHP.
- ~~Reduce the skeleton to a genuine minimum: one route, one ADR triple, one view.~~ **done**.
- Generate reference documentation from `tether context` and `tether explain`, so docs cannot drift.
- Stabilise the public API and commit to semantic versioning at 1.0, where the major becomes the breaking signal.

**Done when** `composer create-project dillonsmart/tetherphp` yields an application with nothing in it that a
developer must first delete.

## Sequencing notes

**Why the minor number is the breaking signal.** Composer reads `^0.3` as locked to the `0.3` series — it will not
accept `0.4.0`. Until 1.0 every breaking change is a minor bump, and the skeleton's constraint must be bumped and
pushed in the same release or `create-project` resolves a mismatched pair. See `docs/agents/releasing.md`.

**The tension to expect.** Phase 4 adds substantial surface area, which reads as a conflict with Small & Composable.
It is not: tooling is not a dependency of the running application. Keep the runtime small and let the CLI be
generous — but keep the CLI's own code out of the request path.

**Explicitness costs typing; pay for it with generators.** Phases 2 and 3 make application code more verbose. That is
the correct trade only if `tether make:*` writes the verbose parts. Every explicit construct introduced in Phase 2
should have a generator by the end of Phase 4.
