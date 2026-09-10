<?php

namespace TetherPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Requests\Request;
use TetherPHP\Router;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    /**
     * Building one no longer starts a session or validates a token, so routing
     * can be unit tested against a plain value — which is what a unit test in
     * this repository is supposed to be able to do.
     */
    private function request(string $method, string $uri): Request
    {
        return new Request($method, $uri, microtime(true));
    }

    public function testRegistersAGetRouteAsStatic(): void
    {
        $this->router->get('/about', 'Actions\About');

        $this->assertSame(
            ['action' => 'Actions\About', 'type' => 'static'],
            $this->router->routes['GET']['/about']
        );
    }

    public function testRegistersAViewRoute(): void
    {
        $this->router->view('/terms', 'pages.terms');

        $this->assertSame(
            ['action' => 'pages.terms', 'type' => 'view'],
            $this->router->routes['GET']['/terms']
        );
    }

    public function testGetAndPostRoutesAreKeptInSeparateTables(): void
    {
        $this->router->get('/contact', 'Actions\ShowContact');
        $this->router->post('/contact', 'Actions\StoreContact');

        $this->assertSame('Actions\ShowContact', $this->router->routes['GET']['/contact']['action']);
        $this->assertSame('Actions\StoreContact', $this->router->routes['POST']['/contact']['action']);
    }

    public function testDetectsDynamicSegments(): void
    {
        $this->router->get('/docs/{page}', 'Actions\Docs');

        $this->assertSame('dynamic', $this->router->routes['GET']['/docs/{page}']['type']);
        $this->assertSame(['page'], $this->router->handleDynamicParts('/docs/{page}'));
    }

    public function testRejectsAnEmptyDynamicSegment(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->router->get('/docs/{}', 'Actions\Docs');
    }

    public function testGroupPrefixesEveryRouteItRegisters(): void
    {
        $this->router->group('admin', function (Router $router) {
            $router->get('/users', 'Actions\Users');
            $router->get('/settings', 'Actions\Settings');
        });

        $this->assertArrayHasKey('/admin/users', $this->router->routes['GET']);
        $this->assertArrayHasKey('/admin/settings', $this->router->routes['GET']);
    }

    public function testGroupPreservesRoutesRegisteredBeforeIt(): void
    {
        $this->router->get('/', 'Actions\Home');

        $this->router->group('admin', function (Router $router) {
            $router->get('/users', 'Actions\Users');
        });

        $this->assertArrayHasKey('/', $this->router->routes['GET']);
        $this->assertArrayHasKey('/admin/users', $this->router->routes['GET']);
    }

    public function testGroupClearsThePrefixAfterwards(): void
    {
        $this->router->group('admin', function (Router $router) {
            $router->get('/users', 'Actions\Users');
        });

        $this->router->get('/login', 'Actions\Login');

        $this->assertArrayHasKey('/login', $this->router->routes['GET']);
    }

    public function testGroupRejectsAnEmptyPrefix(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->router->group('', function (Router $router) {
        });
    }

    public function testResolvesAStaticRoute(): void
    {
        $this->router->get('/about', 'Actions\About');

        $route = $this->router->routeAction($this->request('GET', '/about'));

        $this->assertSame('Actions\About', $route->action);
        $this->assertSame('static', $route->type);
    }

    public function testResolvesADynamicRouteAndCapturesItsParameters(): void
    {
        $this->router->get('/docs/{page}', 'Actions\Docs');

        $route = $this->router->routeAction($this->request('GET', '/docs/routing'));

        $this->assertSame('Actions\Docs', $route->action);
        $this->assertSame('dynamic', $route->type);
        $this->assertSame(['page' => 'routing'], $route->params);
    }

    public function testPrefersAStaticRouteOverADynamicOneOfTheSameShape(): void
    {
        $this->router->get('/docs/{page}', 'Actions\Docs');
        $this->router->get('/docs/index', 'Actions\DocsIndex');

        $route = $this->router->routeAction($this->request('GET', '/docs/index'));

        $this->assertSame('Actions\DocsIndex', $route->action);
    }

    public function testLeavesTheActionUnsetWhenNothingMatches(): void
    {
        $this->router->get('/about', 'Actions\About');

        $route = $this->router->routeAction($this->request('GET', '/nope'));

        $this->assertFalse($route->matched);
    }

    public function testDoesNotMatchADynamicRouteWithADifferentSegmentCount(): void
    {
        $this->router->get('/docs/{page}', 'Actions\Docs');

        $route = $this->router->routeAction($this->request('GET', '/docs/routing/extra'));

        $this->assertFalse($route->matched);
    }

    public function testDoesNotMatchARouteRegisteredForAnotherMethod(): void
    {
        $this->router->post('/contact', 'Actions\StoreContact');

        $route = $this->router->routeAction($this->request('GET', '/contact'));

        $this->assertFalse($route->matched);
    }

    /**
     * Matching ignores case without rewriting the URI.
     *
     * Case-insensitivity used to come from Request lowercasing the URI on the
     * way in, which lowercased everything captured out of it too. The Router
     * compares case-insensitively instead, so `/Posts/My-Slug` matches
     * `/posts/{slug}` and the slug survives.
     */
    public function testAStaticRouteMatchesRegardlessOfCase(): void
    {
        $router = new Router();
        $router->get('/posts', 'Actions\Index');

        $this->assertTrue($router->match('GET', '/POSTS')->matched);
        $this->assertTrue($router->match('GET', '/Posts')->matched);
    }

    public function testADynamicRouteMatchesRegardlessOfCase(): void
    {
        $router = new Router();
        $router->get('/posts/{slug}', 'Actions\Show');

        $this->assertTrue($router->match('GET', '/POSTS/anything')->matched);
    }

    public function testACapturedParameterKeepsTheCaseItWasSentWith(): void
    {
        $router = new Router();
        $router->get('/posts/{slug}', 'Actions\Show');

        $this->assertSame(['slug' => 'My-First-Post'], $router->match('GET', '/posts/My-First-Post')->params);
    }

    public function testACapturedUuidIsNotLowercased(): void
    {
        $router = new Router();
        $router->get('/users/{uuid}', 'Actions\Show');

        $uuid = '3F2504E0-4F89-11D3-9A0C-0305E82C3301';

        $this->assertSame($uuid, $router->match('GET', "/users/{$uuid}")->params['uuid']);
    }

    /**
     * The seven routes of a resource include a static segment that would also
     * satisfy the dynamic one. `/posts/create` must be the create form, not a
     * post whose id is the word "create", whichever order they were registered
     * in.
     */
    public function testAStaticRouteWinsOverADynamicOneRegisteredBeforeIt(): void
    {
        $router = new Router();
        $router->get('/posts/{id}', 'Actions\Show');
        $router->get('/posts/create', 'Actions\Create');

        $this->assertSame('Actions\Create', $router->match('GET', '/posts/create')->action);
        $this->assertSame('Actions\Show', $router->match('GET', '/posts/12')->action);
    }
}
