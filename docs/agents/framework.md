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
bin/tether              # the console binary, declared as Composer `bin`
src/
├── Kernel.php          # boot: error handlers, middleware, dispatch
├── Router.php          # registration, groups, static + dynamic matching
└── framework/
    ├── Commands/       # built-in console commands
    ├── Exceptions/     # HttpException and its status subclasses
    ├── Helpers/        # GlobalFunctions.php (Composer `files`), Route
    ├── Http/           # Response
    ├── Interfaces/     # ActionInterface, DomainResult, MiddlewareInterface, RequestInterface, ResponderInterface
    ├── Middleware/      # VerifyCsrfToken, OverridesMethod
    ├── Modules/        # Console, Input, Env, Log
    ├── Requests/       # Request
    ├── Routing/        # Route (the result of matching)
    ├── Sessions/       # Session, CsrfToken
    ├── Stubs/          # *.txt templates, shared by make:feature and make:resource
    ├── Traits/         # Strings, GeneratesFiles, GeneratesTriples, InspectsApplication
    └── Views/errors/   # fallback error views
tests/
├── Feature/            # needs the fixture application
├── Fixtures/           # the app and routes the bootstrap links into place
└── Unit/
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

## The global functions are a closed list

There are ten, they all live in `src/framework/Helpers/GlobalFunctions.php`, and **the list does not grow without a
principle argument in the commit message.** A global function is ambient by definition, which puts it at odds with
Explicit Over Magic; each of these survives because the alternative — threading an object into a template — costs
more readability than it buys.

| Function                                                | Delegates to                          | Why it exists                                                        |
| ------------------------------------------------------- | ------------------------------------- | -------------------------------------------------------------------- |
| `project_root()`, `package_root()`, `core_dir()`, `core_views()`, `app_dir()`, `storage_dir()`, `views_dir()`, `public_dir()` | Composer's `InstalledVersions`, or `__DIR__` | Pure functions of where the code is installed. They derive a path; they hold nothing and nothing can change them. |
| `env($key, $default = null)`                             | `Env::current()->get()`                | A view or a Domain reading one setting should not have to be handed an `Env` to do it. |
| `logger($message, $level)`                               | `Log::current()->error()` / `->info()` | The same argument, for the same reason.                              |

Only `env()` and `logger()` touch state, and both are **one-line delegates to an object the Kernel installed** —
they make no decision the object does not, and they cannot be the place a bug hides. `Env::current()` and
`Log::current()` have exactly these two callers; a framework class takes an `Env` or a `Log` through its constructor
instead, and calling `current()` from one is a review failure.

Both throw if nothing was installed, naming the fix. That is deliberate: the old `Env::getInstance()` constructed
itself from `project_root()` on first use, so a missing boot looked like a missing variable.

`view()` was removed in `v0.8.0`. It included a template directly, which is what a Responder is for — a second way
to render a page, with none of the data-naming the Responder exists to do.

## PHP version

Every file under `src/` declares `strict_types=1`. That is what turns a silently coerced argument into an error, so
do not omit it from a new file — and be aware it is why `logger($e, 'error')` had to become
`logger($e->getMessage(), 'error')`: an `Exception` was being coerced to a string via `__toString()`, dragging a
stack trace into the log.

`>=8.5`, and the code genuinely depends on the version floor:

- **Property hooks** — `Request::$method` and `$uri` normalise via `set` hooks (`$uri` is lowercased, so route
  matching is case-insensitive and captured dynamic params arrive lowercased).
- **Interface property declarations** — `RequestInterface` declares `public string $method {set; get;}`.
- **`new` without parentheses** — e.g. `new Console($command)->executeCommand(...)`.

- **`get_error_handler()` / `get_exception_handler()`** (8.5) — `Kernel::restoreErrorHandlers()` uses them to
  check its own handler is still the one installed before taking it off, so it cannot clobber a handler someone
  else added afterwards.

Do not "simplify" these into older forms.

## Adding a console command

1. Add the class in `src/framework/Commands/`, extending `Command`, setting `$command` and `$description`.
2. `Console::registerCommands()` globs `src/framework/Commands/*.php` and maps them to
   `TetherPHP\framework\Commands\<Basename>` — the class name must match the filename or it is silently skipped.
3. Registration requires `class_exists()` **and** `is_subclass_of(..., Command::class)`. A command failing either
   check is **skipped with a reason** written to stderr and recorded in `Console::skipped()` — it used to disappear
   silently, which made a misnamed class, a missing psr-4 mapping and a file that was never written
   indistinguishable. Duplicate command names and a missing `$command` are reported the same way.
4. `Console` takes an error stream as its second argument: omit it for stderr, pass a stream to capture diagnostics
   in a test, or pass an explicit `null` to silence them. `null` used to be the default *and* the way to silence,
   and `?? STDERR` turned it straight back into stderr — which is why `tether help` printed every diagnostic twice.

**Only commands go in `Commands/`.** The directory is globbed, so any other class in it is registered, fails the
subclass check and is reported as a broken command. `Input` lives in `Modules/` for exactly that reason.

Applications get their own commands from `app/Commands/` under the `Commands\` namespace; that mapping lives in the
skeleton's `composer.json`.

### Arguments and options

A command declares what it takes, and `Input` parses what it was given:

```php
/** @var array<string, string> */
protected array $arguments = ['name' => 'The name of the feature'];

/** @var array<string, string> */
protected array $options = ['force' => 'Skip the confirmation prompt'];
```

`$this->argument('name')` binds by the **declared order** — the first key is the first positional argument — and
returns `''` when it was not supplied. Asking for an argument the command does not declare throws, because that is a
bug in the command rather than in what the user typed. `$this->option('port', '8000')` and `$this->hasOption('force')`
read the named ones.

The parsing rules are few on purpose: `--name=value`, `--name` (an empty-valued flag), the same in short form, and
`--` to stop parsing so the rest is positional. Everything else is a positional argument.

Declaring an option is also what makes it appear in `tether help <command>`, which is the only reason
`boilerplate:clear` ever knew about `--force`: before `Input` existed it searched the raw argument list for the
literal string.

## Stubs

`make:*` commands read `core_dir() . '/Stubs/*.txt'` and substitute placeholders. **`make:feature` and
`make:resource` share one set** — a feature is a resource with one operation — so there is no flat stub set any
more. `Command.txt` is the exception, because a console command is not an ADR triple.

| Stub | Renders |
| --- | --- |
| `Action.txt` | every Action, in `Actions\{Feature}` |
| `Domain.txt` / `DomainWithInput.txt` | a Domain, with or without constructor input |
| `ResultPage.txt` / `ResultCollection.txt` / `ResultRecord.txt` / `ResultWritten.txt` | the four result shapes — each renders a class named for the shape, not the operation |
| `ResponderPage.txt` / `ResponderCollection.txt` / `ResponderRecord.txt` | a Responder that renders |
| `ResponderRedirect.txt` | a Responder that redirects — what a write answers with |
| `ViewPage.txt` / `ViewIndex.txt` / `ViewShow.txt` / `ViewForm.txt` | the templates |
| `Command.txt` | `make:command` |

| Placeholder            | Substituted by | Used in |
| ---------------------- | -------------- | ------- |
| `{{feature}}`          | every triple generator | every triple stub |
| `{{operation}}`        | every triple generator | every triple stub |
| `{{result}}`           | every triple generator | the results, domains and responders |
| `{{viewName}}`         | every triple generator | the responders and views |
| `{{page}}`             | every triple generator | the responders and views |
| `{{uri}}`              | every triple generator | the views |
| `{{todo}}`             | every triple generator | the domains |
| `{{domainArguments}}`  | every triple generator | `Action.txt` |
| `{{domainProperties}}` | every triple generator | `DomainWithInput.txt` |
| `{{resultArguments}}`  | every triple generator | the domains |
| `{{redirect}}`         | `make:resource`        | `ResponderRedirect.txt` |
| `{{className}}`        | `make:command`         | `Command.txt` only |
| `{{commandName}}`      | `make:command`         | `Command.txt` |

A placeholder no generator substitutes is emitted literally into the developer's file, so the set is a contract —
`tests/Unit/StubsTest.php` enforces it, and also enforces that no triple stub still uses `{{className}}`. Adding one
means updating the stub, `GeneratesTriples`, and that test.

`make:command` derives both names from one argument: `send-welcome-email`, `SendWelcomeEmail` and
`SendWelcomeEmailCommand` all produce class `SendWelcomeEmailCommand` and command name `send-welcome-email`. The
class name is `toValidClassName()` with any redundant `Command` suffix stripped; the command name is
`toKebabCase()` of that. `Command.txt` used to hardcode `tetherphp:command`, so every generated command collided in
the registry — do not reintroduce a literal command name into a stub.

## Every feature is a directory

```
app/
├── Actions/Blog/            Index.php  Show.php
├── Domains/Blog/            Index.php  Show.php
│                            └── Results/  Index.php  Show.php
├── Responders/Blog/         Index.php  Show.php
└── Views/pages/blog/        index.php  show.php
```

`Actions\Blog\Index` → `app/Actions/Blog/Index.php`, and so on. Two rules follow from it:

- **A feature grows by addition.** `make:feature Blog` writes one operation; `make:action Blog Show` puts a second
  beside it and touches nothing. Features used to be flat — `app/Actions/Blog.php` — which meant the second route a
  feature ever needed cost four moved files and four rewritten namespaces. That is a refactor charged for doing the
  thing a framework should make cheap.
- **A Result lives with its Domain**, at `Domains\Blog\Results\Page`, so one feature owns one directory under
  `Domains/`. Results were briefly gathered in a single `Domains\Results\` bucket, which gave every feature two.
- **A Result is named for its shape, and shared.** Actions, Domains and Responders are named for the operation; a
  Result is not, because there are only so many answers a domain gives and naming one per operation produced classes
  that differed from each other by their class name and nothing else. Every operation of a feature that answers the
  same way returns the same Result, so `writeSharedStub()` reuses an existing one instead of failing on it — the one
  place a generator treats "already there" as normal rather than as an error.

`InspectsApplication::conventionalResultClass()` still reports the old bucket for a flat action name whose Result is
actually there, because an application generated before this change has its results in it and the introspection
commands exist to say what is on disk. New code never writes it.

`GeneratesTriples` holds the layout, the operation table and the writers. `make:feature`, `make:resource`,
`make:action`, `make:domain` and `make:responder` are all thin shells over it: read the trait first.

**Which shape you get is predictable from the command.** `make:resource` uses the seven CRUD shapes;
**every other generator writes the page shape** — one page, nothing read off the request. If you want a resource's
Show, run `make:resource`.

### The four result shapes

A `DomainResult` carries what the domain knows into the Responder, named in the domain's terms. Across a CRUD
resource there are only three answers a domain gives, plus one for a page that is neither:

| Shape | Means | Used by | Responder |
| --- | --- | --- | --- |
| `Collection` | many records | `Index` | renders a list, and is handed `$request->query` so it can page and filter |
| `Record` | one record, and its identity | `Create`, `Show`, `Edit` | renders a detail page or a form |
| `Written` | something changed, and which one | `Store`, `Update`, `Destroy` | redirects, 303 |
| `Page` | a page with neither behind it | `make:feature`, and every piecemeal generator | renders |

Two details that are load-bearing rather than stylistic:

- **`Record` keeps `id` separate from `attributes`.** A form posts back to a URL built from the id, and a create
  form has attributes with no id yet — `ViewForm` branches on an empty id to decide between "New" posting to
  `/posts` and "Edit" posting to `/posts/12` with a `_method=PUT` field. That is what lets Create share the shape.
- **`Written` carries the identifier, not the record.** The Responder answers with a redirect and the browser asks
  for the record again, so loading it here would be work the response throws away. `Destroy` is the loosest fit: it
  reports the id it deleted and its redirect goes to the collection, so the generated code does not read it.

These are starting shapes, not a fixed vocabulary. The generator cannot know a domain, so it writes the three that
CRUD always needs; renaming them and their properties for what the domain actually deals in is the expected next
step, and the stubs say so.

## Generating a CRUD resource

`make:resource <Name>` writes the seven ADR triples a resource is made of — `Index`, `Create`, `Store`, `Show`,
`Edit`, `Update`, `Destroy` — each with its own Action, Domain and Responder, the three Results they share between
them, and the four views that render. `--uri=/posts` sets the base URI the routes and redirects use; it defaults to the kebab-cased name.

**There is no pluraliser and there is not going to be one.** Guessing that `Post` becomes `posts` and `Category`
becomes `categories` is a table of English irregulars that will be wrong about the one word an application cares
about.

**There is no `$router->resource()` either.** One line registering seven routes would put the route table somewhere
a reader cannot see it, which is the auto-discovery Principle 3 forbids. The command prints the seven lines for
`routes/web.php` instead, where they stay visible to a reader, to `tether routes` and to `tether explain`. Nothing
is written into an application's route table by a generator.

It writes the layout `make:feature` writes and shares its stubs — see above — so a feature that grows into a
resource gains files rather than moving them.

Two constraints the generated code works within, both worth knowing before editing a stub:

- **A Domain takes its input through its constructor, never through `handle()`.** `Domains\Domain` declares
  `handle(): DomainResult` with no parameters and PHP will not let an override add a required one, so a stub
  generating `handle(string $id)` fatals on autoload. The Action reads the id off `$request->params` and the fields
  off `$request->payload` and hands both over — the same rule the Kernel follows with its Env and Log.
- **A write answers with a redirect, not a page.** `Store`, `Update` and `Destroy` return `Response::redirect(...)`
  with a **303**, not the default 302: only 303 is defined to make the next request a GET whatever this one was,
  which is what Post/Redirect/Get depends on after a PUT or a DELETE.

## The introspection commands

`routes`, `explain`, `inspect` and `context` exist because of Principle 6 — a runtime feature is not finished until
the tooling can show it. They **only report**; none of them changes an application, and none instantiates an Action,
Domain or Responder to look at it — doing so would run a constructor that builds two more objects.

They share `Traits\InspectsApplication`, which loads `project_root() . '/routes/web.php'` and flattens the table, and
`routes/middleware.php` for what wraps it — see below for the contract that makes loading the second one safe.

**The Result is read, not guessed.** The Domain and the Responder are found by name and the output says so, but
`triple()` reflects on `Domain::handle()` for the Result — including a **union**, which is how a Domain says a
section can end more than one way (`Post|PostNotFound`, with the Responder picking the view and the status off the
type). A union is reported pipe-separated with no file path under it, because it names several. It has to: Results are shared by shape, so `Show` and
`Edit` both return `Results\Record` and there is no `Results\Show` to predict — guessing reported "(not found)"
for every action in every resource. Reading the declaration is also the standard the rest of this output is held
to, so each part now carries a `declared` flag and `inspect` marks which is which. Reflection reads a signature and
constructs nothing; the rule below still holds.
`Router::match()` exists so they can resolve a URI without a `Request` — building one starts a session and enforces
CSRF, which is right inside a request and impossible from a terminal.

Where the output links an Action to a Domain and Responder it says **by convention**, and it means it: an Action
constructs its own in its constructor and may use anything. The Result is the exception — it says **declared**,
because it came from the code. The commands report what is on disk under the
conventional name, and mark what is missing.

`context` writes JSON to stdout and nothing else, so it can be piped. Its contract is the done-when for Phase 4 of
the roadmap — an agent given only that output should be able to say where a new feature's files belong and which
route would conflict — and `tests/Feature/IntrospectionTest.php` asserts the keys that promise depends on.

## The console binary

`bin/tether` is the entry point, declared as `"bin": ["bin/tether"]` in this package's `composer.json`. Composer
writes a proxy to the consuming application's `vendor/bin/tether` on install, and the skeleton's root `tether` file
is a shim that forwards to that proxy. So the console bootstrap lives here, once, and no generated application
carries a copy of it that can drift.

It finds the autoloader through `$_composer_autoload_path`, which Composer's proxy sets:

```php
$autoload = $_composer_autoload_path ?? __DIR__ . '/../vendor/autoload.php';
```

The fallback covers running `bin/tether` from a standalone checkout of this repository, where there is no proxy and
no parent `vendor/`. Do not replace it with a fixed relative path — the correct `vendor/` is the *application's*, not
this package's, and only the proxy knows where that is.

Two things follow:

- Changing how the console boots — argument parsing, the autoloader lookup, the exit code — is a change here, not in
  the skeleton.
- **Adding or removing the `bin` declaration is a consumer-visible change.** Composer only writes `vendor/bin/`
  proxies at install time, so an application resolving an older release gets no `vendor/bin/tether` and its shim
  fails. A release that changes the declaration needs the skeleton's constraint bumped past it — see
  `docs/agents/releasing.md`.

## The pipeline

`Kernel::run()` returns a `Response`. Every path through it does — a match, a
miss, a rejected write, a misconfigured route. Nothing is echoed and nothing
calls `exit()`, which is what makes the Kernel testable at all; it had no
coverage until this changed.

```
Request → [ Middleware → Route → Action → Domain → Responder → Response ] → Middleware
```

Middleware **wraps** the pipeline rather than being a stage in it: the first in
the list is the outermost, so it is the first to see a request and the last to
see a response.

- **`Response`** (`framework/Http`) is an immutable value: body, status, headers.
  `send()` is the only place the framework writes to the client.
- **`Route`** (`framework/Routing`) says whether it matched. It replaced
  `RouteDTO`, which signalled "not found" by leaving a typed property
  uninitialised for callers to test with `isset()`.
- **Actions must implement `ActionInterface`** and return a `Response`. The
  Kernel checks the instance and 500s with a log line if it does not; before,
  only `is_callable` was checked.
- **All three sources of input arrive on the request**: `$request->params` from
  the path, `$request->query` from the query string, `$request->payload` from
  the body. Each is a plain public array read with `??`; there is deliberately
  no `input()` accessor over the top, which would be a second way to read the
  same value and would hide which of the three it came from. The router always
  captured parameters and the Kernel dropped them; the query string was parsed
  off the URI and thrown away; the body was `$_POST`, which PHP fills for a POST
  and nothing else.
- **Domains return a `DomainResult`, never an array.** See below — this is the
  one part of the pipeline the framework constrains by type without owning any
  of the classes involved.

## Middleware: the composition seam

`MiddlewareInterface` is one method:

```php
public function __invoke(Request $request, \Closure $next): Response;
```

Call `$next($request)` to continue and you get the Response from the rest of the
pipeline, to return, replace or add a header to. Return your own Response
without calling it and nothing further runs. Throw an `HttpException` and the
Kernel turns it into the error page, exactly as it does from an Action.

The list is given to the Kernel; nothing is discovered. **The order middleware
runs in is the order it is written in `public/index.php` and nowhere else.**

Two decisions worth not relitigating:

- **Why `$next` rather than a before-only guard returning `?Response`.** The
  guard is easier to read and cannot touch the response on the way out, which
  means a second concept the first time anything needs to — a security header,
  a timing measurement. One shape covers both, and it is the shape PHP
  developers and agents already recognise.
- **Why the Kernel turns `HttpException` into a Response *inside* the
  middleware.** If a 404 were thrown past them, middleware that adds something
  on the way out would silently not apply to error pages, which is the one class
  of response you least want to miss. `run()` still catches, for a middleware
  that throws before calling `$next` and so has no inner pipeline to be caught
  by.

### What it made possible

`Middleware\VerifyCsrfToken` is the first thing to use it, and the reason the
seam was built before anything was extracted. `Middleware\OverridesMethod` is
the second, and it is what makes full CRUD reachable from a browser.

CSRF used to be validated inside `Request::__construct()`. A Request took a
Session, could throw, and every test that needed one needed a session — so the
check could not be turned off, replaced, or applied to some routes and not
others, and an API-only application got a session whether it wanted one or not.
The Kernel constructed a `Session` and a `CsrfToken` on every request whether
anything used them.

Now an application composes it in, and one that leaves it out boots with no
session and no CSRF check. `tests/Feature/CsrfProtectionTest.php` asserts both
halves, including the application that opts out.

`Middleware\OverridesMethod` is the second. A form element sends GET or POST and
nothing else, so the `put()`, `patch()` and `delete()` routes the Router has
offered since `v0.4.0` were unreachable from a page — the U and the D of CRUD
worked for a JSON client and for nothing a browser could render. The middleware
reads `_method` out of the body of a POST and rewrites the verb before routing:

```html
<form method="post" action="/posts/12">
    <input type="hidden" name="_method" value="PUT">
```

It is the one piece of behaviour in the framework triggered by a magic field
name, **which is exactly why it is a middleware and not something the Kernel
does**. Composed in, it appears in `routes/middleware.php` where a reader can
see it and in the output of `tether routes` and `tether explain`. Left out,
`_method` is an ordinary form field with no meaning. Two rules it keeps:

- **Only a POST is upgraded.** A link carrying `?_method=DELETE` must not delete
  anything.
- **Only into PUT, PATCH or DELETE.** Overriding into GET would turn a
  submission into something a browser thinks it may repeat.

Compose it above `VerifyCsrfToken`. Both orders are safe — the request is a
write before and after the override — but the CSRF middleware logs the verb it
rejected, and the verb the application asked for is the more useful one to read.

### Where the list is declared, and why it moved

`routes/middleware.php`, next to `routes/web.php`. Everything in `routes/`
answers one question: `web.php` says where a request goes, `middleware.php` says
what it passes through. The file returns a callable, the way `web.php` does:

```php
return function (Env $env, Log $log): array {
    return [
        new VerifyCsrfToken(new Session(), $log),
    ];
};
```

It was declared in `public/index.php` for one release, and that made it
invisible to the tooling: those commands must not load the file that boots and
serves the application. `tether explain` claimed to show "the path a URI takes"
while hiding the first thing that happens to it.

Moving it was not enough on its own, because **the console has to build the
list to read the class names off it** — and `new Session()` called
`session_start()` in its constructor, so listing an application's middleware
would have started a session from a terminal. Sessions start lazily now, on
first use.

That is the contract the whole approach rests on, and it belongs in any
middleware anyone writes:

> **Building a middleware must have no side effects.** Do the work in
> `__invoke()`.

`VerifyCsrfToken` holds a `Session` and is in the test fixture's list precisely
to prove it: `IntrospectionTest::testBuildingTheMiddlewareListStartsNoSession`
asserts `session_status()` is unchanged after `tether routes` runs.

The rule that these commands never construct application objects still stands
for Actions, Domains and Responders — instantiating an Action runs a constructor
that builds two more objects. Middleware is the one exception, it is declared in
a file written to be loaded, and it is bought with a stated contract rather than
with a hope.

## Why `DomainResult` exists

`Domain::handle()` returned `array<string, mixed>`. The Responder passed that array to `view()`, which hands it to
`extract()`, so its **keys were the template's variable names**. Renaming `$tagline` in a view meant editing a
business-logic class — precisely the coupling the Responder sits in the pipeline to absorb, and the reason the
Responder had become a pass-through with a name.

`framework/Interfaces/DomainResult.php` is an empty marker. It declares nothing because it has nothing to say about
what a result holds; its job is to give `Domain::handle()` and `Action::respond()` a type that is neither `array`
nor `object`, so the pipeline can be read.

The rules that follow from it, enforced by `tests/Unit/StubsTest.php` rather than by the framework at runtime:

- a result is a `final readonly` value object under `Domains\Results\`, named and shaped in the **domain's** terms
- the **Responder** builds the array the view extracts. It is the only place a view variable may be named
- a feature with more than one outcome gets more than one result type. A list result and a single result are
  different classes, not one array with a `found` flag in it — the Responder can then `match` on the type and pick
  the view *and* the status code

The framework cannot enforce any of this: `Actions\`, `Domains\` and `Responders\` are application namespaces, and
the framework must not depend on them. It ships the interface, the stubs and the tests that hold the stubs to the
contract; the skeleton demonstrates it.

## Request handling invariants

- **Route on the path, not `REQUEST_URI`.** The Kernel splits the query string off with `parse_url`; routing on the
  raw value meant any URL carrying parameters 404'd. The query string is not discarded — it reaches the Action as
  `$request->query`, which is what lets an index page paginate, filter or search.
- **Matching ignores case; the URI is not rewritten to achieve it.** `Request` used to lowercase the URI through a
  property hook, which lowercased every parameter captured out of it — so `/posts/{slug}` could not carry a slug
  with a capital and `/users/{uuid}` was broken for half the UUIDs there are. `Router::match()` compares
  case-insensitively and captures segments verbatim. Do not put normalisation back on `Request`.
- **The body is parsed for every verb.** `Kernel::payload()` decodes JSON, prefers `$_POST` where PHP has filled it
  (the only thing that can read a multipart body — `php://input` is empty for those, so file uploads would be lost),
  and otherwise parses a form-encoded body itself. Before this, an update sent as a form-encoded PUT arrived empty:
  the route matched, the Action ran, and every field was missing.
- **The body is built before middleware runs, not during dispatch.** `Middleware\OverridesMethod` has to read it,
  and reading `$request->payload` early used to be a fatal "must not be accessed before initialization" rather than
  an empty array. `Kernel::body()` is `protected` because `php://input` is always empty under the CLI, so a test
  that needs to send a form-encoded PUT overrides it — `tests/Fixtures/KernelWithBody.php`. Nothing else should.
- **Never index `$routes[$method]` directly.** Only GET and POST can be registered, so any other verb used to hit a
  missing key and take the request down with a TypeError. `Router::routesFor()` is the only accessor; it defaults to
  an empty table and answers HEAD from the GET one.
- **Errors end a request by throwing an `HttpException`.** `Kernel::run()` is the only catch: an `HttpException`
  becomes an error Response carrying its status, title, description and any headers it declares; any other
  `Throwable` is logged and becomes a 500. A route miss throws `HttpNotFoundException`, a rejected write
  `HttpForbiddenException`, a misconfigured route `HttpInternalServerErrorException`. The framework ships those
  three; applications subclass `HttpException` for their own statuses.
- **Error pages go through `Kernel::errorResponse()`**, which prefers the application's `app/Views/errors/{status}.php`
  and falls back to the framework's own. The view is included with `$status`, `$title` and `$description` in scope.
  It returns the body rather than echoing it, so `run()` returns what it says it returns.
- **A rejected write is a 403, not a 500.** `VerifyCsrfToken` throws `HttpForbiddenException`; the reason goes to
  the log, not to the visitor.
- **The CSRF token is read from `$request->payload` or the `X-CSRF-Token` header.** It used to be read from `$_POST`
  directly, which PHP only populates for POST bodies, so the hidden field could not authorise a PUT, PATCH or
  DELETE and the header was the only way. Now that the Kernel parses every body the field works on every verb; the
  header stays for fetch/XHR clients and for multipart uploads.
- **Only POST, PUT, PATCH and DELETE are challenged**, named rather than inverted. "Anything that is not a GET"
  would refuse an OPTIONS preflight with a 403 instead of letting it resolve to no route.

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
that means typing the request/session boundary — do not close the gap with casts or `@phpstan-ignore`.

Cover behaviour that consumers depend on: route resolution order (static wins over dynamic), case-insensitive
matching with parameters captured verbatim, group prefixing, the path helpers, the three sources of request input,
CSRF acceptance and rejection, and the stub placeholder contract.

`tests/Feature/MakeResourceTest.php` runs `make:resource` into the fixture application and removes what it wrote in
both `setUp()` and `tearDown()`, so an interrupted run cannot leave the next one asserting against stale files.

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
