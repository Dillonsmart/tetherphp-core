<?php

declare(strict_types=1);

namespace TetherPHP\framework\Traits;

use TetherPHP\framework\Interfaces\ActionInterface;
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
     * @return array<string, array{class: string, exists: bool, file: ?string}>
     */
    protected function triple(string $action): array
    {
        $short = $this->shortName($action);

        $classes = [
            'action' => $action,
            'domain' => "Domains\\{$short}",
            'result' => "Domains\\Results\\{$short}",
            'responder' => "Responders\\{$short}",
        ];

        $triple = [];

        foreach ($classes as $role => $class) {
            $triple[$role] = [
                'class' => $class,
                'exists' => class_exists($class),
                'file' => $this->classFile($class),
            ];
        }

        return $triple;
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
