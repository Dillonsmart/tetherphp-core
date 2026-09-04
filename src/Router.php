<?php

declare(strict_types=1);

namespace TetherPHP;

use TetherPHP\framework\DTOs\RouteDTO;
use TetherPHP\framework\Requests\Request;

class Router {
    /** @var array{GET: array<string, array<string, mixed>>, POST: array<string, array<string, mixed>>} */
    public array $routes = [
        'GET' => [],
        'POST' => []
    ];

    public string $prefix = '';

    public string $uri = '';

    public string $action = '';

    public function view(string $uri, string $view): void {
        $this->routes['GET'][$this->makeUri($uri)] = [
            'action' => $view,
            'type' => 'view'
        ];
    }

    public function get(string $uri, string $action): void {
        $this->uri = $uri;
        $this->action = $action;

        $this->routes['GET'][$this->makeUri($uri)] = $this->buildRoute();
    }

    public function post(string $uri, string $action): void {
        $this->uri = $uri;
        $this->action = $action;

        $this->routes['POST'][$this->makeUri($uri)] = $this->buildRoute();
    }

    public function makeUri(string $uri): string {
        return $this->prefix . $uri;
    }

    /** @return array{action: string, type: string, parts: list<string>} */
    public function buildRoute(): array
    {
        return [
            'action' => $this->action,
            'type' => $this->hasDynamicParts($this->uri) ? 'dynamic' : 'static',
            'parts' => $this->hasDynamicParts($this->uri) ? $this->handleDynamicParts($this->uri) : []
        ];
    }

    public function hasDynamicParts(string $uri): bool {
        return str_contains($uri, '{') && str_contains($uri, '}');
    }

    /** @return list<string> */
    public function handleDynamicParts(string $uri): array
    {
        $dynamicParts = explode('{', $uri);

        $validParts = [];

        foreach ($dynamicParts as $part) {
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

    public function group(string $prefix, callable $callback): void {
        if (empty($prefix)) {
            throw new \InvalidArgumentException("Prefix cannot be empty.");
        }

        if ($prefix[0] !== '/') {
            $prefix = '/' . $prefix;
        }

        $this->prefix = $prefix;

        $originalRoutes = $this->routes;
        $this->routes = [
            'GET' => [],
            'POST' => []
        ];

        // routes registered inside the callback pick up $this->prefix as they go
        $callback($this);

        $this->routes['GET'] = array_merge($originalRoutes['GET'], $this->routes['GET']);
        $this->routes['POST'] = array_merge($originalRoutes['POST'], $this->routes['POST']);
        $this->prefix = '';
    }

    public function routeAction(Request $request): RouteDTO {
        $routeObject = new RouteDTO();
        $routes = $this->routesFor($request->method);

        if(array_key_exists($request->uri, $routes)) {
            $routeObject->action = $routes[$request->uri]['action'];
            $routeObject->type = $routes[$request->uri]['type'];

            return $routeObject;
        }

        foreach ($routes as $uri => $route) {
            if ($route['type'] === 'dynamic') {
                $parts = explode('/', $uri);
                $requestParts = explode('/', $request->uri);

                if (count($parts) !== count($requestParts)) {
                    continue;
                }

                $params = [];
                $isMatch = true;

                foreach ($parts as $index => $part) {
                    if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
                        $params[trim($part, '{}')] = $requestParts[$index];
                    } elseif ($part !== $requestParts[$index]) {
                        $isMatch = false;
                        break;
                    }
                }

                if ($isMatch) {
                    $routeObject->action = $route['action'];
                    $routeObject->type = 'dynamic';
                    $routeObject->params = $params;

                    // first match wins; without this the last registered route
                    // silently overwrote every earlier one
                    return $routeObject;
                }
            }
        }

        return $routeObject;
    }

    /**
     * Routes registered for a method.
     *
     * Only GET and POST can be registered, so any other verb — HEAD from a link
     * checker, OPTIONS from a CORS preflight — used to index a missing key and
     * take the whole request down with a TypeError. HEAD is answered from the
     * GET table because HTTP defines it as GET without a body.
     *
     * @return array<string, array<string, mixed>>
     */
    private function routesFor(string $method): array
    {
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        return $this->routes[$method] ?? [];
    }
}