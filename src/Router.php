<?php

declare(strict_types=1);

namespace TetherPHP;

use TetherPHP\framework\Requests\Request;
use TetherPHP\framework\Routing\Route;

class Router
{
    /**
     * Registered routes, keyed by method then URI.
     *
     * @var array<string, array<string, array{action: string, type: string}>>
     */
    public array $routes = [];

    public string $prefix = '';

    /**
     * The verbs a route can be registered for. Request enforces CSRF on every
     * one of these except GET, so the two halves have to agree about which
     * verbs exist — previously only GET and POST could be registered while
     * PUT, PATCH and DELETE were CSRF-checked but unroutable.
     *
     * @var list<string>
     */
    private const array METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public function get(string $uri, string $action): void
    {
        $this->add('GET', $uri, $action);
    }

    public function post(string $uri, string $action): void
    {
        $this->add('POST', $uri, $action);
    }

    public function put(string $uri, string $action): void
    {
        $this->add('PUT', $uri, $action);
    }

    public function patch(string $uri, string $action): void
    {
        $this->add('PATCH', $uri, $action);
    }

    public function delete(string $uri, string $action): void
    {
        $this->add('DELETE', $uri, $action);
    }

    /**
     * Renders a view with no Action behind it.
     *
     * Still a single path through the pipeline: the Kernel resolves it to the
     * framework's own view rendering rather than a second way of handling a
     * request.
     */
    public function view(string $uri, string $view): void
    {
        $this->routes['GET'][$this->makeUri($uri)] = [
            'action' => $view,
            'type' => Route::TYPE_VIEW,
        ];
    }

    public function group(string $prefix, callable $callback): void
    {
        if (empty($prefix)) {
            throw new \InvalidArgumentException("Prefix cannot be empty.");
        }

        if ($prefix[0] !== '/') {
            $prefix = '/' . $prefix;
        }

        $previous = $this->prefix;
        $this->prefix = $previous . $prefix;

        // routes registered inside the callback pick up $this->prefix as they go
        $callback($this);

        $this->prefix = $previous;
    }

    public function makeUri(string $uri): string
    {
        return $this->prefix . $uri;
    }

    public function hasDynamicParts(string $uri): bool
    {
        return str_contains($uri, '{') && str_contains($uri, '}');
    }

    /**
     * @return list<string>
     */
    public function handleDynamicParts(string $uri): array
    {
        $validParts = [];

        foreach (explode('{', $uri) as $part) {
            if (str_contains($part, '}')) {
                $part = explode('}', $part)[0];

                if (empty($part)) {
                    throw new \InvalidArgumentException("Dynamic part cannot be empty.");
                }

                $validParts[] = $part;
            }
        }

        return $validParts;
    }

    public function routeAction(Request $request): Route
    {
        $routes = $this->routesFor($request->method);
        $uri = $request->uri;

        if (array_key_exists($uri, $routes)) {
            return Route::to($routes[$uri]['action'], $routes[$uri]['type']);
        }

        foreach ($routes as $pattern => $route) {
            if ($route['type'] !== Route::TYPE_DYNAMIC) {
                continue;
            }

            $params = $this->matchDynamic($pattern, $uri);

            if ($params !== null) {
                // first match wins; without returning here the last registered
                // route silently overwrote every earlier one
                return Route::to($route['action'], Route::TYPE_DYNAMIC, $params);
            }
        }

        return Route::none();
    }

    /**
     * @return array<string, string>|null the captured parameters, or null if the pattern does not match
     */
    private function matchDynamic(string $pattern, string $uri): ?array
    {
        $parts = explode('/', $pattern);
        $requestParts = explode('/', $uri);

        if (count($parts) !== count($requestParts)) {
            return null;
        }

        $params = [];

        foreach ($parts as $index => $part) {
            if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
                $params[trim($part, '{}')] = $requestParts[$index];
                continue;
            }

            if ($part !== $requestParts[$index]) {
                return null;
            }
        }

        return $params;
    }

    private function add(string $method, string $uri, string $action): void
    {
        $dynamic = $this->hasDynamicParts($uri);

        if ($dynamic) {
            // validates the segments and rejects '{}'. This used to happen as a
            // side effect of computing a 'parts' key nothing ever read; the
            // check is worth keeping, so it is done deliberately.
            $this->handleDynamicParts($uri);
        }

        $this->routes[$method][$this->makeUri($uri)] = [
            'action' => $action,
            'type' => $dynamic ? Route::TYPE_DYNAMIC : Route::TYPE_STATIC,
        ];
    }

    /**
     * Routes registered for a method.
     *
     * HEAD is answered from the GET table because HTTP defines it as GET
     * without a body. An unregistered verb returns an empty table rather than
     * indexing a missing key and taking the request down.
     *
     * @return array<string, array{action: string, type: string}>
     */
    private function routesFor(string $method): array
    {
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        return $this->routes[$method] ?? [];
    }

    /**
     * The verbs a route can be registered for.
     *
     * @return list<string>
     */
    public static function methods(): array
    {
        return self::METHODS;
    }
}
