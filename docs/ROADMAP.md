# TetherPHP refactor roadmap

Taking the framework from working to compliant with its [six core principles](../AGENTS.md#the-six-core-principles).
Ordered by dependency: Phases 2 and 3 are both breaking and ship together so consumers migrate once.

Current: `v0.8.0`. Target: `v1.0`.

Items struck through below have landed since this roadmap was written.

## The spine

```
Request → Route → Action → Domain → Responder → Response
```

Every stage now has a representation in code and the Kernel has one exit. Phase 2 was entirely about that; what is
left is what the pipeline cannot yet be *asked about* (Phase 4) and what it cannot yet be *extended with* (Phase 5).

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

**`v0.5.0` raises the floor to PHP 8.5**, which is what let `Kernel::restoreErrorHandlers()` exist: 8.5's
`get_error_handler()` makes it possible to check the Kernel's own handler is still installed before removing it,
rather than blindly calling `restore_error_handler()` and risking someone else's. The Kernel previously installed a
pair per construction with no way to take them off.

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

## Phase 2 — One explicit pipeline · `v0.4.0`, breaking — **done**

Give the last stage of the pipeline a body, and make the Kernel have exactly one exit.

- Introduce a `Response` value object — status, headers, body. `Kernel::run(): Response`, and `public/index.php` is
  the only thing that emits.
- Remove every `include` and `exit()` from `Kernel`. 404 and 500 become ordinary `Response` values, and testable.
- Enforce `ActionInterface`: `__invoke(): Response`.
- Replace `RouteDTO` with a `Route` that states whether it matched.
- Resolve the two rendering paths. Recommended: keep `$router->view()` as sugar resolving to a framework-supplied
  Action, so there is one path through the pipeline and one way to write a static page.

**Done.** Every route returns a `Response`; `Kernel::run()` reads top to bottom; the Kernel has tests for the first
time. `Router` also gained `put`, `patch` and `delete`, which `Request` had been enforcing CSRF on all along without
them being registerable.

**Risk** Every existing Action's signature changes. The skeleton and the website update in lockstep with the release.

## Phase 3 — Explicit dependencies · `v0.8.0`, breaking — **done**

Remove the ambient state. What a thing needs should arrive through its constructor.

- ~~Retire the `Env` singleton. Construct once in `Kernel` and pass it down; `getInstance()` goes.~~ **done** — `Env`
  is an immutable value object built by `Env::fromFile()`, and `Kernel::__construct()` takes one. A missing key
  returns a default instead of throwing an exception for `env()` to catch, log and swallow.
- ~~`Log` becomes an instance with a configured destination.~~ **done** — the directory is a constructor argument,
  so `Log` no longer reaches for `storage_dir()` and a Kernel under test logs somewhere harmless.
- ~~Decide the global-function contract deliberately.~~ **done** — ten functions, documented with a reason each in
  `docs/agents/framework.md`, and closed. Eight are pure functions of the install path; `env()` and `logger()` are
  one-line delegates to the objects the Kernel installed. `view()` was **removed**: it included a template directly,
  which is a second way to render a page and does none of the data-naming a Responder exists for.
- ~~Collapse `toValidClassName()` into `toPascalCase()`.~~ **done**.

**Done.** No framework class reaches for global state to do its job. `Env::current()` and `Log::current()` have
exactly two callers between them — the two helpers — and both throw, naming the fix, rather than lazily constructing
themselves from `project_root()` the way `getInstance()` did.

**Ship with Phase 2** — one migration guide, one upgrade.

**What it did not reach.** PHPStan level 9. `$_SERVER` and `$_POST` still enter `Kernel` untyped and `Session::get()`
still returns `mixed`, so values derived from them are `mixed` too. Typing that boundary is its own change — it is
also what would finally expose the query string, which no application can currently read.

## Phase 4 — The CLI becomes the product · `v0.8.0` — **done**

Principle 6 taken literally: the framework should explain the application, not merely run it.

- ~~A real argument and option parser.~~ **done** — `Modules\Input`. Options were never parsed at all: `bin/tether`
  passed a literal empty array for them, which is why `boilerplate:clear` searched its own raw argument list for the
  string `--force`. Commands declare `$arguments` and `$options`, and both appear in `tether help <command>`.
- ~~`make:action`, `make:domain`, `make:responder`~~ **done**, on the same writer as `make:feature`, so a feature
  generated whole and one generated a piece at a time cannot drift apart. `make:domain` writes the Result with the
  Domain and `make:responder` the view with the Responder, because neither half loads without the other.
- ~~`tether routes`~~ **done** — and it marks a route whose Action is missing or not routable, which was previously
  a 500 nobody saw until someone requested it.
- ~~`tether explain <uri>`~~ **done** — resolves the URI the way a request would, lowercased and without its query
  string, and names the parameters it would capture.
- ~~`tether inspect <class>`~~ **done**.
- ~~`tether context`~~ **done** — JSON on stdout: routes, ADR triples, commands, directories, namespaces and the
  rules that are otherwise folklore.
- ~~`tether test` and `tether serve`~~ **done**. `test` forwards to the application's own PHPUnit rather than
  vendoring a runner into core.

**Done.** `tests/Feature/IntrospectionTest.php` asserts the keys the done-when depends on, against a fixture route
table that includes a broken route and an unroutable one.

Two things fell out of the work. `Console`'s error stream could not actually be silenced — `null` was both the
default and the way to ask for silence, so `tether help` printed every registration diagnostic twice. And the Kernel
defined `VERSION` and `VERSION_NAME` from two hand-maintained properties that nothing read and that still said
"0.5 alpha" at v0.7.0; they are gone, and `context` asks Composer what is installed.

## Phase 5 — Seams and packages · `v0.9.0` — **in progress**

Prove that "small core, composed beyond" is a real architecture.

The plan was written as though the seam already existed. It did not: there was nowhere for an extracted concern to
plug back in, so extracting one would have produced a package that could not be used. The seam came first.

- ~~A seam to compose onto.~~ **done** — `MiddlewareInterface`, one method, given to the Kernel as a list. It wraps
  routing as well as the Action, and the Kernel converts `HttpException` to a Response *inside* it, so middleware
  that adds something on the way out applies to error pages too.
- ~~Extract one concern to prove the seam.~~ **done** — CSRF. It was validated inside `Request::__construct()`,
  which took a `Session` and could throw, so the check could not be turned off, replaced or scoped, every test that
  needed a Request needed a session, and the Kernel built a `Session` and a `CsrfToken` on every request whether or
  not anything used them. It is `Middleware\VerifyCsrfToken` now, composed in by the application; leave it out and
  the application boots with no session and no CSRF check.
- **Not done: the physical package split.** `Session`, `CsrfToken` and `VerifyCsrfToken` still ship inside core.
  Nothing in the request path references them, so moving them to their own repository is now mechanical — but it is
  a packaging step (a new repository, a Packagist entry, a `require`) rather than a code change, and the done-when
  below is only half met until it happens.
- **Not done: interfaces for the other replaceable concerns.** Deliberately. Session storage, logging and view
  rendering each have one implementation and no second in prospect, and the phase's own warning applies — an
  interface with nothing to swap in is complexity charged against Human First. Write them when something real needs
  to replace one.

**Done when** an application can boot without the session package installed, and the core has no reference to it.
The first half holds today and is tested. The second waits on the repository split.

**The debt this created, and paid.** Middleware was declared in `public/index.php`, which the introspection commands
must not load, so nothing could show what runs around a request — a Principle 6 failure by the standard Phase 4 set.

It is declared in `routes/middleware.php` now, beside `routes/web.php`: `web.php` says where a request goes, this
says what it passes through. Moving it was only half the fix, because the console has to *build* the list to read the
class names off it, and `new Session()` called `session_start()` in its constructor — listing middleware would have
started a session from a terminal. Sessions start lazily now, on first use, which is the framework's own rule that a
returned value beats a side effect applied to a constructor that had been quietly breaking it since the beginning.

`routes`, `explain` and `context` all report the list in order. The contract that buys it — **building a middleware
must have no side effects; do the work in `__invoke()`** — is documented, and asserted by a test that checks
`session_status()` is unchanged after `tether routes` runs.

**Watch** This phase can quietly become an abstraction exercise. Only extract a concern something real would replace;
an interface with one implementation and no prospect of a second is complexity charged against Human First.

## Phase 6 — Surface · `v1.0` track

- ~~Move the documentation website out of the skeleton into its own project.~~ **done** — it now lives in the
  private `tetherphp-website` repository, itself built on TetherPHP.
- ~~Reduce the skeleton to a genuine minimum: one route, one ADR triple, one view.~~ **done**.
- ~~Give the skeleton a test suite.~~ **done** — `tests/Unit` and `tests/Feature` split the way ADR does, a
  `Tests\TestCase` that sends a request through the real Kernel, and `php tether test`. The command shipped in
  Phase 4 pointing at a `vendor/bin/phpunit` that a generated application did not have.
- Generate reference documentation from `tether context` and `tether explain`, so docs cannot drift.
- Stabilise the public API and commit to semantic versioning at 1.0, where the major becomes the breaking signal.

Both remaining items are decisions rather than refactors. Documentation generation needs somewhere to put the output
(the website repository) and a format; the 1.0 freeze needs a judgement about which of `Request`, `Response`,
`Router`, `Kernel`, `MiddlewareInterface` and the console's own output are being promised, and the seam is one
release old.

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
