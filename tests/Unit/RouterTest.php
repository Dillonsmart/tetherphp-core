<?php

namespace TetherPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Requests\Request;
use TetherPHP\framework\Sessions\CsrfToken;
use TetherPHP\framework\Sessions\Session;
use TetherPHP\Router;

class RouterTest extends TestCase
{
    private Router $router;

    private Session $session;

    protected function setUp(): void
    {
        $this->router = new Router();

        $_SESSION = [];
        $this->session = new Session();
        new CsrfToken($this->session);
    }

    private function request(string $method, string $uri): Request
    {
        return new Request($this->session, $method, $uri, microtime(true));
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
}
