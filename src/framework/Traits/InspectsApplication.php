<?php

declare(strict_types=1);

namespace TetherPHP\framework\Traits;

use TetherPHP\framework\Interfaces\ActionInterface;
use TetherPHP\framework\Interfaces\DomainResult;
use TetherPHP\framework\Interfaces\MiddlewareInterface;
use TetherPHP\framework\Modules\Env;
use TetherPHP\framework\Modules\Log;
use TetherPHP\Router;

/**
 * What the introspection commands need to know about the application they are
 * pointed at.
 *
 * `routes`, `explain` and `context` all start by answering the same two
 * questions — what routes are registered, and what is on the other end of one —
 * so the answers live here rather than three times over.
 *
 * Nothing here loads an application class to look at it. Reflection and
 * class_exists() are enough, and instantiating an Action to find out what it is
 * would run its constructor, which builds a Domain and a Responder and is
 * emphatically not what `inspect` should do.
 */
trait InspectsApplication
{
    /**
     * The application's route table, or null with a reason already printed.
     */
    protected function applicationRouter(): ?Router
    {
        $file = project_root() . '/routes/web.php';

        if (!is_file($file)) {
            $this->error("No route file at {$file}.");

            return null;
        }

        $definition = require $file;

        if (!is_callable($definition)) {
            $this->error("{$file} must return a callable that takes a Router.");

            return null;
        }

        $router = new Router();
        $definition($router);

        return $router;
    }

    /**
     * The middleware that runs around every request, in order.
     *
     * This is the one thing the tooling could not see. Middleware was declared
     * in `public/index.php`, which these commands must not load — it boots and
     * serves the application. Moving the declaration to `routes/middleware.php`
     * put it somewhere loadable, and making `Session` start lazily rather than
     * in its constructor made loading it safe.
     *
     * **Building a middleware must have no side effects.** That is the contract
     * this depends on: the console constructs the list purely to read the class
     * names off it, so a middleware that opens a connection or writes a cookie
     * in its constructor does that from a terminal too. Do the work in
     * `__invoke()`, which is where it belongs anyway.
     *
     * A missing file is not a problem — an application may run no middleware —
     * but a file that cannot be built is reported rather than swallowed.
     *
     * @return array{names: list<string>, problem: ?string}
     */
    protected function applicationMiddleware(): array
    {
        $file = project_root() . '/routes/middleware.php';

        if (!is_file($file)) {
            return ['names' => [], 'problem' => null];
        }

        $definition = require $file;

        if (!is_callable($definition)) {
            return [
                'names' => [],
                'problem' => "{$file} must return a callable that takes an Env and a Log.",
            ];
        }

        try {
            $middleware = $definition($this->consoleEnv(), $this->consoleLog());
        } catch (\Throwable $e) {
            return [
                'names' => [],
                'problem' => 'Could not build the middleware: ' . $e->getMessage(),
            ];
        }

        if (!is_array($middleware)) {
            return ['names' => [], 'problem' => "{$file} must return a list of middleware."];
        }

        $names = [];

        foreach ($middleware as $one) {
            if (!$one instanceof MiddlewareInterface) {
                $names[] = (is_object($one) ? $one::class : get_debug_type($one))
                    . '  (does not implement MiddlewareInterface)';

                continue;
            }

            $names[] = $one::class;
        }

        return ['names' => $names, 'problem' => null];
    }

    /**
     * An Env for building the middleware list with. An application that has not
     * been configured yet still has a route table worth printing, so a missing
     * .env is an empty environment rather than a failure.
     */
    private function consoleEnv(): Env
    {
        $file = project_root() . '/.env';

        return is_file($file) ? Env::fromFile($file) : new Env();
    }

    private function consoleLog(): Log
    {
        return new Log(storage_dir() . 'logs/');
    }

    /**
     * Every registered route, flattened, in a shape both the table and the JSON
     * can be built from.
     *
     * @return list<array{method: string, uri: string, type: string, action: string}>
     */
    protected function routeList(Router $router): array
    {
        $routes = [];

        foreach ($router->routes as $method => $table) {
            foreach ($table as $uri => $route) {
                $routes[] = [
                    'method' => $method,
                    'uri' => $uri,
                    'type' => $route['type'],
                    'action' => $route['action'],
                ];
            }
        }

        usort($routes, static fn (array $a, array $b): int => [$a['uri'], $a['method']] <=> [$b['uri'], $b['method']]);

        return $routes;
    }

    /**
     * The ADR triple an Action sits in, found by name.
     *
     * This is a convention, not a wiring table: an Action constructs its own
     * Domain and Responder and could name them anything. `Actions\Blog` almost
     * always means `Domains\Blog` and `Responders\Blog`, and saying so is
     * useful — as long as the output says "by convention" and reports what is
     * actually on disk rather than implying the framework resolved it.
     *
     * The whole name after `Actions\` is carried across, not just the last
     * part of it. Every feature is a namespace, so `Actions\Post\Show` belongs
     * to `Domains\Post\Show`; matching on the short name alone looked for
     * `Domains\Show` and reported every action in every feature as missing its
     * triple.
     *
     * @return array<string, array{class: string, exists: bool, file: ?string, declared: bool}>
     *         `class` is pipe-separated when a Domain declares a union of Result types
     */
    protected function triple(string $action): array
    {
        $name = str_starts_with($action, 'Actions\\')
            ? substr($action, strlen('Actions\\'))
            : $this->shortName($action);

        $domain = "Domains\\{$name}";

        // the Action is what the route names, so it is not a guess; the Result
        // is read off the Domain's own signature where the Domain declares one.
        // Each role carries a list because a Domain may declare a union — the
        // outcome-per-type pattern, where a miss is its own Result type
        $roles = [
            'action' => [[$action], true],
            'domain' => [[$domain], false],
            'result' => $this->resultClass($domain, $name),
            'responder' => [["Responders\\{$name}"], false],
        ];

        $triple = [];

        foreach ($roles as $role => [$classes, $declared]) {
            $missing = array_filter($classes, static fn (string $class): bool => !class_exists($class));

            $triple[$role] = [
                'class' => implode('|', $classes),
                'exists' => $classes !== [] && $missing === [],
                // a union names several files, and pointing at one of them
                // would be picking a favourite
                'file' => count($classes) === 1 ? $this->classFile($classes[0]) : null,
                'declared' => $declared,
            ];
        }

        return $triple;
    }

    /**
     * The Result a Domain returns, read off `handle()` rather than guessed.
     *
     * Guessing stopped working when Results became shared by shape. There are
     * only three answers a CRUD domain gives — many, one, or "I changed this" —
     * so `Show` and `Edit` both return `Domains\Post\Results\Record` and
     * there is no `Results\Show` to look for. Predicting one by name would
     * have reported "(not found)" for every action in every resource.
     *
     * Reading the declaration is also simply more honest: it reports what the
     * code says instead of what the naming implies, which is the standard the
     * rest of this trait's output is held to. Reflection here inspects a
     * signature and constructs nothing — the introspection commands must never
     * instantiate an Action, Domain or Responder, because that runs a
     * constructor which builds two more objects.
     *
     * The conventional names remain the fallback, for a Domain that is missing,
     * has no `handle()` yet, or returns the `DomainResult` interface itself.
     *
     * @return array{0: list<string>, 1: bool} the classes, and whether the code declared them
     */
    protected function resultClass(string $domain, string $name): array
    {
        $declared = $this->declaredResultTypes($domain);

        if ($declared !== []) {
            return [$declared, true];
        }

        return [[$this->conventionalResultClass($name)], false];
    }

    /**
     * The Result types `handle()` declares, which may be more than one.
     *
     * A union is the framework's own documented way of saying a section can end
     * more than one way — `Post|PostNotFound`, with the Responder picking both
     * the view and the status off the type. Reading only a single named type
     * meant every domain written that way fell back to the naming convention
     * and was reported as missing, which is the one shape of Domain most worth
     * being able to explain.
     *
     * @return list<string> empty when nothing useful is declared
     */
    private function declaredResultTypes(string $domain): array
    {
        if (!class_exists($domain)) {
            return [];
        }

        $class = new \ReflectionClass($domain);

        if (!$class->hasMethod('handle')) {
            return [];
        }

        $type = $class->getMethod('handle')->getReturnType();

        $members = $type instanceof \ReflectionUnionType ? $type->getTypes() : [$type];

        $names = [];

        foreach ($members as $member) {
            if (!$member instanceof \ReflectionNamedType || $member->isBuiltin()) {
                continue;
            }

            // the base class declares the interface; a subclass that has not
            // narrowed it tells us nothing a reader does not already know
            if ($member->getName() === DomainResult::class) {
                continue;
            }

            $names[] = $member->getName();
        }

        return $names;
    }

    /**
     * Where a Result sits when its Domain cannot say.
     *
     * Results are nested under the feature — `Domains\Blog\Results\Page`
     * beside `Domains\Blog\Index` — so that one feature owns one directory
     * under `Domains/`. They used to sit in a single top-level bucket, and an
     * application generated before that changed still has them there.
     *
     * The current convention is what gets reported unless the old bucket
     * actually holds something. Naming the old place for a Result that exists
     * in neither would send someone to create a file where nothing else lives.
     */
    private function conventionalResultClass(string $name): string
    {
        $position = strrpos($name, '\\');

        if ($position === false) {
            // Actions\Home, from before features had a directory each
            return "Domains\\Results\\{$name}";
        }

        $feature = substr($name, 0, $position);
        $operation = substr($name, $position + 1);

        $nested = "Domains\\{$feature}\\Results\\{$operation}";
        $legacy = "Domains\\Results\\{$name}";

        return !class_exists($nested) && class_exists($legacy) ? $legacy : $nested;
    }

    protected function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    protected function classFile(string $class): ?string
    {
        if (!class_exists($class)) {
            return null;
        }

        $file = new \ReflectionClass($class)->getFileName();

        if ($file === false) {
            return null;
        }

        $root = project_root() . '/';

        return str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
    }

    protected function isRoutable(string $class): bool
    {
        return class_exists($class) && is_subclass_of($class, ActionInterface::class);
    }
}
