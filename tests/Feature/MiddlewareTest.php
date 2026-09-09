<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Interfaces\MiddlewareInterface;
use TetherPHP\framework\Modules\Env;
use TetherPHP\framework\Modules\Log;
use TetherPHP\Kernel;
use TetherPHP\Router;
use TetherPHP\Tests\Fixtures\app\Actions\Greet;
use TetherPHP\Tests\Fixtures\app\Middleware\AddsHeader;
use TetherPHP\Tests\Fixtures\app\Middleware\Records;
use TetherPHP\Tests\Fixtures\app\Middleware\Refuses;
use TetherPHP\Tests\Fixtures\app\Middleware\Throws;

/**
 * The seam everything else is meant to compose onto.
 *
 * Nothing could run between a Request and an Action before this: requiring a
 * login on ten routes meant the same guard pasted into ten Actions, and a
 * package had nowhere to attach at all.
 */
class MiddlewareTest extends TestCase
{
    /** @var list<Kernel> */
    private array $kernels = [];

    private Router $router;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/greet';

        Records::$log = [];

        $this->router = new Router();
        $this->router->get('/greet', Greet::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->kernels as $kernel) {
            $kernel->restoreErrorHandlers();
        }

        $this->kernels = [];
    }

    /**
     * @param list<MiddlewareInterface> $middleware
     */
    private function get(string $uri, array $middleware): Response
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $uri;

        $kernel = new Kernel(
            $this->router,
            new Env(['APP_DEBUG' => 'false']),
            new Log(sys_get_temp_dir() . '/tether-middleware-test-logs'),
            $middleware,
        );

        $this->kernels[] = $kernel;

        return $kernel->run();
    }

    public function testAKernelWithNoMiddlewareBehavesExactlyAsBefore(): void
    {
        $response = $this->get('/greet', []);

        $this->assertSame(200, $response->status());
        $this->assertSame('hello world', $response->body());
    }

    /**
     * Work on the way out. A before-only guard could not do this, which is why
     * the contract takes $next.
     */
    public function testMiddlewareCanChangeTheResponseOnTheWayBack(): void
    {
        $response = $this->get('/greet', [new AddsHeader('X-Frame-Options', 'DENY')]);

        $this->assertSame('hello world', $response->body());
        $this->assertSame('DENY', $response->headers()['X-Frame-Options']);
    }

    public function testMiddlewareCanAnswerTheRequestItself(): void
    {
        $response = $this->get('/greet', [new Refuses()]);

        $this->assertSame(401, $response->status());
        $this->assertSame('refused', $response->body());
    }

    /**
     * Short-circuiting has to stop everything after it, not just the Action.
     */
    public function testNothingAfterARefusalRuns(): void
    {
        $this->get('/greet', [new Refuses(), new Records('inner')]);

        $this->assertSame([], Records::$log);
    }

    /**
     * A middleware ends a request early the same way an Action does — by
     * throwing — and run() turns it into the error Response.
     */
    public function testMiddlewareCanRefuseByThrowing(): void
    {
        $response = $this->get('/greet', [new Throws()]);

        $this->assertSame(403, $response->status());
        $this->assertStringContainsString('403 Forbidden', $response->body());
    }

    /**
     * First in the list is the outermost layer: first to see the request, last
     * to see the response. Anything else would surprise whoever wrote the list.
     */
    public function testTheFirstMiddlewareInTheListIsTheOutermost(): void
    {
        $this->get('/greet', [new Records('first'), new Records('second')]);

        $this->assertSame(
            ['enter first', 'enter second', 'leave second', 'leave first'],
            Records::$log,
        );
    }

    /**
     * Middleware wraps routing, not just the Action, so it runs for a request
     * that goes on to 404 — and sees the 404 Response on the way back out.
     *
     * The 404 is raised by throwing, so this only works because the Kernel
     * turns HttpExceptions into Responses inside the middleware rather than
     * outside it. Otherwise a security-header middleware would apply to every
     * page except the error pages.
     */
    public function testMiddlewareSeesTheResponseEvenWhenNothingMatches(): void
    {
        $response = $this->get('/nothing-here', [new AddsHeader('X-Seen', 'yes')]);

        $this->assertSame(404, $response->status());
        $this->assertSame('yes', $response->headers()['X-Seen']);
    }

    /**
     * The same for an error raised by an Action rather than by routing.
     */
    public function testMiddlewareSeesAResponseAnActionThrew(): void
    {
        $this->router->get('/teapot', \TetherPHP\Tests\Fixtures\app\Actions\Brew::class);

        $response = $this->get('/teapot', [new AddsHeader('X-Seen', 'yes')]);

        $this->assertSame(418, $response->status());
        $this->assertSame('yes', $response->headers()['X-Seen']);
    }

    public function testAMiddlewareCanAnswerBeforeRoutingEverHappens(): void
    {
        $response = $this->get('/nothing-here', [new Refuses()]);

        $this->assertSame(401, $response->status());
    }
}
