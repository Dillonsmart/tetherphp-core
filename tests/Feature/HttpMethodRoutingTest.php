<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Requests\Request;
use TetherPHP\framework\Sessions\CsrfToken;
use TetherPHP\framework\Sessions\Session;
use TetherPHP\Router;

/**
 * The router only accepts GET and POST registrations, so every other verb used
 * to index a missing key in $routes and bring the request down with a TypeError
 * — HEAD from a link checker, OPTIONS from a CORS preflight, both 500s.
 */
class HttpMethodRoutingTest extends TestCase
{
    private Router $router;

    private Session $session;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];

        $this->session = new Session();
        new CsrfToken($this->session);

        $this->router = new Router();
        $this->router->get('/', 'Actions\Home');
    }

    private function request(string $method, string $uri = '/'): Request
    {
        return new Request($this->session, $method, $uri, microtime(true));
    }

    /** @return list<list<string>> */
    public static function unregisteredMethodProvider(): array
    {
        return [['OPTIONS'], ['PUT'], ['PATCH'], ['DELETE'], ['TRACE']];
    }

    #[DataProvider('unregisteredMethodProvider')]
    public function testAnUnregisteredMethodResolvesToNoRouteRatherThanCrashing(string $method): void
    {
        $_POST = ['csrf_token' => $this->session->get('csrf_token')];

        $route = $this->router->routeAction($this->request($method));

        $this->assertFalse(isset($route->action));
    }

    /**
     * HTTP defines HEAD as GET without a body, so it is answered from the GET
     * table rather than treated as an unknown verb.
     */
    public function testHeadIsAnsweredFromTheGetTable(): void
    {
        $route = $this->router->routeAction($this->request('HEAD'));

        $this->assertSame('Actions\Home', $route->action);
    }

    public function testHeadStillMissesWhereGetWouldMiss(): void
    {
        $route = $this->router->routeAction($this->request('HEAD', '/nope'));

        $this->assertFalse(isset($route->action));
    }

    /**
     * Dynamic matching used to run to completion and keep the last match, so a
     * later route silently shadowed an earlier one.
     */
    public function testTheFirstMatchingDynamicRouteWins(): void
    {
        $this->router->get('/thing/{id}', 'Actions\First');
        $this->router->get('/thing/{slug}', 'Actions\Second');

        $route = $this->router->routeAction($this->request('GET', '/thing/42'));

        $this->assertSame('Actions\First', $route->action);
        $this->assertSame(['id' => '42'], $route->params);
    }
}
