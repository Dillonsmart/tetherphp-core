<?php

declare(strict_types=1);

namespace TetherPHP\framework\Traits;

use TetherPHP\framework\Interfaces\ActionInterface;
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
