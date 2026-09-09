<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Modules\Env;
use TetherPHP\framework\Modules\Log;
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

    /** @var list<Kernel> */
    private array $kernels = [];

    protected function tearDown(): void
    {
        // the framework takes its own handlers back off now, rather than the
        // tests papering over a leak
        foreach ($this->kernels as $kernel) {
            $kernel->restoreErrorHandlers();
        }

        $this->kernels = [];
    }

    /**
     * The Kernel is handed an environment and a log rather than finding them.
     * A test can now state the settings it is exercising — APP_DEBUG here —
     * without writing a .env file into the repository.
     */
    private function kernel(): Kernel
    {
        $kernel = new Kernel($this->router, $this->env(), $this->log());
        $this->kernels[] = $kernel;

        return $kernel;
    }

    private function env(): Env
    {
        return new Env(['APP_NAME' => 'TetherPHP Tests', 'APP_DEBUG' => 'false']);
    }

    private function log(): Log
    {
        return new Log(sys_get_temp_dir() . '/tether-kernel-test-logs');
    }

    private function get(string $uri): Response
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $uri;

        return $this->kernel()->run();
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

    /**
     * An Action ends a request early by throwing — the Kernel turns the
     * exception into the error Response rather than the Action building one.
     */
    public function testAnActionCanThrowItsOwn404(): void
    {
        $this->router->get('/users/{id}', \TetherPHP\Tests\Fixtures\app\Actions\ThrowsNotFound::class);

        $this->assertSame(404, $this->get('/users/99')->status());
    }

    /**
     * Applications subclass HttpException for their own statuses; the headers
     * the exception carries must land on the response.
     */
    public function testAnApplicationHttpExceptionCarriesItsStatusAndHeaders(): void
    {
        $this->router->get('/brew', \TetherPHP\Tests\Fixtures\app\Actions\Brew::class);

        $response = $this->get('/brew');

        $this->assertSame(418, $response->status());
        $this->assertSame('tea', $response->headers()['X-Beverage']);
    }

    public function testACrashBecomesA500WithoutLeakingTheMessage(): void
    {
        $this->router->get('/crash', \TetherPHP\Tests\Fixtures\app\Actions\Crashes::class);

        $response = $this->get('/crash');

        $this->assertSame(500, $response->status());
        $this->assertStringNotContainsString('hunter2', $response->body());
    }

    /**
     * A view route pointing at a missing view used to return the 500 error
     * page with a 200 status — the body said one thing, the status another.
     */
    public function testAMissingViewIsA500NotA200(): void
    {
        $this->router->view('/ghost', 'pages.does-not-exist');

        $this->assertSame(500, $this->get('/ghost')->status());
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

        $response = $this->kernel()->run();

        $this->assertSame(403, $response->status());
    }

    /**
     * The fixture app ships no errors/403.php, so the framework fallback
     * renders — with the exception's title and description in scope.
     */
    public function testTheForbiddenPageRendersTheExceptionTitleAndDescription(): void
    {
        $this->router->post('/save', \TetherPHP\Tests\Fixtures\app\Actions\Greet::class);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/save';
        $_POST = [];

        $response = $this->kernel()->run();

        $this->assertStringContainsString('403 Forbidden', $response->body());
        $this->assertStringContainsString('You are not allowed to access this resource.', $response->body());
    }

    public function testAnUnregisteredMethodReturns404RatherThanCrashing(): void
    {
        $this->router->get('/', \TetherPHP\Tests\Fixtures\app\Actions\Greet::class);

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/';

        $this->assertSame(404, $this->kernel()->run()->status());
    }

    /**
     * The Kernel used to install handlers with no way to remove them, so every
     * construction leaked a pair. PHP 8.5's get_error_handler() makes the
     * removal checkable.
     */
    public function testItTakesItsOwnErrorHandlersBackOff(): void
    {
        $before = get_error_handler();

        $kernel = new Kernel($this->router, $this->env(), $this->log());
        $this->assertNotSame($before, get_error_handler(), 'the Kernel should install its handler');

        $kernel->restoreErrorHandlers();
        $this->assertSame($before, get_error_handler(), 'and put the previous one back');
    }

    /**
     * If something else installed a handler after the Kernel did, restoring
     * must not pull that one off the stack.
     */
    public function testItDoesNotRemoveAHandlerInstalledAfterIt(): void
    {
        $kernel = new Kernel($this->router, $this->env(), $this->log());

        $mine = static fn (): bool => true;
        set_error_handler($mine);

        $kernel->restoreErrorHandlers();

        $this->assertSame($mine, get_error_handler());

        restore_error_handler();
        restore_error_handler();
        restore_exception_handler();
    }

    public function testHeadIsServedFromTheGetTable(): void
    {
        $this->router->get('/greet', \TetherPHP\Tests\Fixtures\app\Actions\Greet::class);

        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '/greet';

        $this->assertSame(200, $this->kernel()->run()->status());
    }
}
