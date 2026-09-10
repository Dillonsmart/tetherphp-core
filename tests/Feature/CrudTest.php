<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Middleware\OverridesMethod;
use TetherPHP\framework\Modules\Env;
use TetherPHP\framework\Modules\Log;
use TetherPHP\framework\Interfaces\MiddlewareInterface;
use TetherPHP\Kernel;
use TetherPHP\Router;
use TetherPHP\Tests\Fixtures\app\Actions\Echoes;
use TetherPHP\Tests\Fixtures\KernelWithBody;

/**
 * The four verbs a CRUD resource needs, and the three places its input arrives.
 *
 * Create, read, update and delete were each half-supported: the routes could be
 * registered but a browser could not reach three of them, the query string was
 * parsed off the URI and dropped, and the body was read from $_POST, which PHP
 * only populates for a POST. This asserts the whole surface a resource uses.
 */
class CrudTest extends TestCase
{
    private Router $router;

    /** @var list<Kernel> */
    private array $kernels = [];

    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        unset($_SERVER['CONTENT_TYPE']);

        $this->router = new Router();
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->kernels) as $kernel) {
            $kernel->restoreErrorHandlers();
        }

        $this->kernels = [];
        unset($_SERVER['CONTENT_TYPE']);
    }

    /**
     * @param list<MiddlewareInterface> $middleware
     */
    private function send(string $method, string $uri, string $body = '', string $contentType = '', array $middleware = []): Response
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;

        if ($contentType !== '') {
            $_SERVER['CONTENT_TYPE'] = $contentType;
        }

        $kernel = new KernelWithBody(
            $this->router,
            new Env(['APP_NAME' => 'TetherPHP Tests', 'APP_DEBUG' => 'false']),
            new Log(sys_get_temp_dir() . '/tether-crud-test-logs'),
            $middleware,
        );

        $kernel->requestBody = $body;
        $this->kernels[] = $kernel;

        return $kernel->run();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $decoded = json_decode($response->body(), true);

        $this->assertIsArray($decoded);

        return $decoded;
    }

    private function form(): string
    {
        return 'application/x-www-form-urlencoded';
    }

    // -- Read: the query string ------------------------------------------

    /**
     * The roadmap listed this as the thing typing the request boundary would
     * finally expose: requestPath() split the query string off so `/posts?page=2`
     * could match `/posts`, and then dropped it. An index page could not
     * paginate, filter or search.
     */
    public function testTheQueryStringReachesTheAction(): void
    {
        $this->router->get('/posts', Echoes::class);

        $body = $this->decode($this->send('GET', '/posts?page=2&q=tether'));

        $this->assertSame(['page' => '2', 'q' => 'tether'], $body['query']);
    }

    public function testAnAbsentQueryStringIsAnEmptyArrayRatherThanUnset(): void
    {
        $this->router->get('/posts', Echoes::class);

        $this->assertSame([], $this->decode($this->send('GET', '/posts'))['query']);
    }

    public function testTheQueryStringIsStillNotMatchedOn(): void
    {
        $this->router->get('/posts', Echoes::class);

        $this->assertSame(200, $this->send('GET', '/posts?page=2')->status());
    }

    // -- Read and update: route parameters keep their case ----------------

    /**
     * Request lowercased the URI through a property hook, which made routing
     * case-insensitive by destroying the URI: every parameter captured out of
     * it arrived lowercased, so no resource could be identified by a slug or a
     * UUID. The Router compares case-insensitively instead.
     */
    public function testARouteParameterKeepsTheCaseItWasSentWith(): void
    {
        $this->router->get('/posts/{slug}', Echoes::class);

        $body = $this->decode($this->send('GET', '/posts/My-First-Post'));

        $this->assertSame(['slug' => 'My-First-Post'], $body['params']);
    }

    public function testMatchingIsStillCaseInsensitive(): void
    {
        $this->router->get('/posts/{slug}', Echoes::class);

        $this->assertSame(200, $this->send('GET', '/POSTS/abc')->status());
    }

    public function testTheUriReachesTheActionAsItWasSent(): void
    {
        $this->router->get('/posts/{slug}', Echoes::class);

        $this->assertSame('/Posts/Abc', $this->decode($this->send('GET', '/Posts/Abc'))['uri']);
    }

    // -- Create and update: the body --------------------------------------

    public function testAFormEncodedPostBodyReachesTheAction(): void
    {
        $this->router->post('/posts', Echoes::class);

        $_POST = ['title' => 'Hello'];

        $this->assertSame(['title' => 'Hello'], $this->decode($this->send('POST', '/posts', '', $this->form()))['payload']);
    }

    /**
     * The one that was silently broken. PHP populates $_POST for a POST body
     * and nothing else, so a form-encoded update arrived empty: the route
     * matched, the Action ran, and every field was missing.
     */
    public function testAFormEncodedPutBodyReachesTheAction(): void
    {
        $this->router->put('/posts/{id}', Echoes::class);

        $body = $this->decode($this->send('PUT', '/posts/12', 'title=Hello&body=World', $this->form()));

        $this->assertSame(['title' => 'Hello', 'body' => 'World'], $body['payload']);
    }

    public function testAFormEncodedPatchBodyReachesTheAction(): void
    {
        $this->router->patch('/posts/{id}', Echoes::class);

        $this->assertSame(
            ['title' => 'Hello'],
            $this->decode($this->send('PATCH', '/posts/12', 'title=Hello', $this->form()))['payload'],
        );
    }

    public function testAJsonPutBodyReachesTheAction(): void
    {
        $this->router->put('/posts/{id}', Echoes::class);

        $body = $this->decode($this->send('PUT', '/posts/12', '{"title":"Hello"}', 'application/json'));

        $this->assertSame(['title' => 'Hello'], $body['payload']);
    }

    public function testADeleteWithNoBodyHasAnEmptyPayload(): void
    {
        $this->router->delete('/posts/{id}', Echoes::class);

        $this->assertSame([], $this->decode($this->send('DELETE', '/posts/12'))['payload']);
    }

    /**
     * php://input is empty for multipart/form-data, so parsing the raw body by
     * hand would lose every file upload. Where PHP has already filled $_POST it
     * stays the source.
     */
    public function testAPopulatedPostSuperglobalWinsOverTheRawBody(): void
    {
        $this->router->post('/posts', Echoes::class);

        $_POST = ['title' => 'From $_POST'];

        $body = $this->decode($this->send('POST', '/posts', 'title=From+the+stream', 'multipart/form-data; boundary=x'));

        $this->assertSame(['title' => 'From $_POST'], $body['payload']);
    }

    // -- Update and delete: the method override ---------------------------

    /**
     * A form element sends GET or POST and nothing else, so the put(), patch()
     * and delete() routes the Router has offered since v0.4.0 were unreachable
     * from a page.
     */
    public function testAFormCanAskForPut(): void
    {
        $this->router->put('/posts/{id}', Echoes::class);

        $_POST = ['_method' => 'PUT', 'title' => 'Hello'];

        $body = $this->decode($this->send('POST', '/posts/12', '', $this->form(), [new OverridesMethod()]));

        $this->assertSame('PUT', $body['method']);
        $this->assertSame('12', $body['params']['id']);
        $this->assertSame('Hello', $body['payload']['title']);
    }

    public function testAFormCanAskForDelete(): void
    {
        $this->router->delete('/posts/{id}', Echoes::class);

        $_POST = ['_method' => 'delete'];

        $this->assertSame('DELETE', $this->decode($this->send('POST', '/posts/12', '', $this->form(), [new OverridesMethod()]))['method']);
    }

    /**
     * The override is a middleware, not Kernel behaviour, so an application
     * that has not composed it in has no magic field name.
     */
    public function testWithoutTheMiddlewareTheFieldIsAnOrdinaryFormField(): void
    {
        $this->router->delete('/posts/{id}', Echoes::class);

        $_POST = ['_method' => 'DELETE'];

        $this->assertSame(404, $this->send('POST', '/posts/12', '', $this->form())->status());
    }

    /**
     * A link carrying ?_method=DELETE must not delete anything.
     */
    public function testAGetIsNeverOverridden(): void
    {
        $this->router->get('/posts/{id}', Echoes::class);

        $this->assertSame(
            'GET',
            $this->decode($this->send('GET', '/posts/12?_method=DELETE', '', '', [new OverridesMethod()]))['method'],
        );
    }

    public function testOverridingIntoGetIsRefused(): void
    {
        $this->router->post('/posts', Echoes::class);

        $_POST = ['_method' => 'GET'];

        $this->assertSame('POST', $this->decode($this->send('POST', '/posts', '', $this->form(), [new OverridesMethod()]))['method']);
    }

    /**
     * The override reads the parsed body, which the Kernel used to assign
     * during dispatch — after every middleware had already run. Reading it
     * early was not an empty array but a fatal "must not be accessed before
     * initialization", so this is the test that the body is built up front.
     */
    public function testTheOverrideReadsABodyThatOnlyTheStreamCarried(): void
    {
        $this->router->put('/posts/{id}', Echoes::class);

        $body = $this->decode($this->send('POST', '/posts/12', '_method=PUT&title=Hello', $this->form(), [new OverridesMethod()]));

        $this->assertSame('PUT', $body['method']);
        $this->assertSame('Hello', $body['payload']['title']);
    }

    // -- The whole resource -----------------------------------------------

    /**
     * The seven routes `make:resource` prints, resolving the way it says they
     * do — including the static /posts/create winning over the dynamic
     * /posts/{id} regardless of the order they were registered in.
     */
    public function testTheSevenRoutesOfAResourceEachResolve(): void
    {
        $this->router->get('/posts', Echoes::class);
        $this->router->get('/posts/{id}', Echoes::class);
        $this->router->get('/posts/create', Echoes::class);
        $this->router->get('/posts/{id}/edit', Echoes::class);
        $this->router->post('/posts', Echoes::class);
        $this->router->put('/posts/{id}', Echoes::class);
        $this->router->delete('/posts/{id}', Echoes::class);

        $middleware = [new OverridesMethod()];

        $this->assertSame(200, $this->send('GET', '/posts')->status());
        $this->assertSame(200, $this->send('GET', '/posts/create')->status());
        $this->assertSame([], $this->decode($this->send('GET', '/posts/create'))['params'], 'create is static, not an id');
        $this->assertSame('12', $this->decode($this->send('GET', '/posts/12'))['params']['id']);
        $this->assertSame('12', $this->decode($this->send('GET', '/posts/12/edit'))['params']['id']);
        $this->assertSame(200, $this->send('POST', '/posts', 'title=Hello', $this->form())->status());

        $_POST = ['_method' => 'PUT'];
        $this->assertSame('PUT', $this->decode($this->send('POST', '/posts/12', '', $this->form(), $middleware))['method']);

        $_POST = ['_method' => 'DELETE'];
        $this->assertSame('DELETE', $this->decode($this->send('POST', '/posts/12', '', $this->form(), $middleware))['method']);
    }
}
