<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Http\Response;
use TetherPHP\Kernel;
use TetherPHP\Router;

/**
 * The Kernel had no test coverage at all until it stopped calling exit().
 *
 * Every path now returns a Response, which is the whole point of the change:
 * a 404, a rejected write and a misconfigured route are values that can be
 * asserted on rather than side effects that end the process.
 */
class KernelTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        $this->router = new Router();
    }

    /**
     * Kernel::setErrorHandler() installs global handlers and offers no way to
     * remove them, so each construction leaves a pair behind. Worth fixing in
     * the framework; until then the tests put the stack back themselves.
     */
    protected function tearDown(): void
    {
        // one pair per Kernel constructed, and each test constructs exactly one
        restore_error_handler();
        restore_exception_handler();
    }

    private function get(string $uri): Response
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $uri;

        return (new Kernel($this->router))->run();
    }

    public function testAMatchedRouteReturnsTheActionsResponse(): void
    {
        $this->router->get('/greet', \TetherPHP\Tests\Fixtures\app\Actions\Greet::class);

        $response = $this->get('/greet');

        $this->assertSame(200, $response->status());
        $this->assertSame('hello world', $response->body());
    }

    /**
     * The router captured parameters and the Kernel threw them away, so the
     * documented {name} syntax did not work and applications re-parsed the URI
     * by hand.
     */
    public function testRouteParametersReachTheAction(): void
    {
        $this->router->get('/greet/{name}', \TetherPHP\Tests\Fixtures\app\Actions\Greet::class);

        $this->assertSame('hello ada', $this->get('/greet/ada')->body());
    }

    public function testAQueryStringDoesNotPreventAMatch(): void
    {
        $this->router->get('/greet', \TetherPHP\Tests\Fixtures\app\Actions\Greet::class);

        $this->assertSame(200, $this->get('/greet?utm_source=x')->status());
    }

    public function testAnUnmatchedRouteReturnsA404Response(): void
    {
        $response = $this->get('/nothing-here');

        $this->assertSame(404, $response->status());
        $this->assertStringContainsString('404', $response->body());
    }

    public function testAMissingActionClassReturns500(): void
    {
        $this->router->get('/broken', 'Actions\DoesNotExist');

        $this->assertSame(500, $this->get('/broken')->status());
    }

    /**
     * ActionInterface existed but nothing enforced it; the Kernel only checked
     * is_callable, so a class without __invoke got as far as being called.
     */
    public function testAClassThatIsNotAnActionReturns500(): void
    {
        $this->router->get('/nope', \TetherPHP\Tests\Fixtures\app\Actions\NotAnAction::class);

        $this->assertSame(500, $this->get('/nope')->status());
    }

    public function testAWriteWithoutACsrfTokenIsRejectedAsForbidden(): void
    {
        $this->router->post('/save', \TetherPHP\Tests\Fixtures\app\Actions\Greet::class);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/save';
        $_POST = [];

        $response = (new Kernel($this->router))->run();

        $this->assertSame(403, $response->status());
    }

    public function testAnUnregisteredMethodReturns404RatherThanCrashing(): void
    {
        $this->router->get('/', \TetherPHP\Tests\Fixtures\app\Actions\Greet::class);

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/';

        $this->assertSame(404, (new Kernel($this->router))->run()->status());
    }

    public function testHeadIsServedFromTheGetTable(): void
    {
        $this->router->get('/greet', \TetherPHP\Tests\Fixtures\app\Actions\Greet::class);

        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '/greet';

        $this->assertSame(200, (new Kernel($this->router))->run()->status());
    }
}
