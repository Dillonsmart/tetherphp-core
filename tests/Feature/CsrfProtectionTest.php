<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Middleware\VerifyCsrfToken;
use TetherPHP\framework\Modules\Env;
use TetherPHP\framework\Modules\Log;
use TetherPHP\framework\Sessions\CsrfToken;
use TetherPHP\framework\Sessions\Session;
use TetherPHP\Kernel;
use TetherPHP\Router;
use TetherPHP\Tests\Fixtures\app\Actions\Greet;

/**
 * CSRF protection through the seam it now hangs on.
 *
 * These used to construct a Request directly, because a Request validated its
 * own token in its constructor. That coupling is gone: the check is a
 * middleware an application composes in, so the tests exercise it the way an
 * application gets it — through the Kernel.
 */
class CsrfProtectionTest extends TestCase
{
    private Session $session;

    private Router $router;

    /** @var list<Kernel> */
    private array $kernels = [];

    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);

        $this->session = new Session();

        $this->router = new Router();

        foreach (['get', 'post', 'put', 'patch', 'delete'] as $verb) {
            $this->router->{$verb}('/contact', Greet::class);
        }
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);

        foreach ($this->kernels as $kernel) {
            $kernel->restoreErrorHandlers();
        }

        $this->kernels = [];
    }

    private function withToken(): string
    {
        new CsrfToken($this->session);

        $token = $this->session->get('csrf_token');
        $this->assertIsString($token);

        return $token;
    }

    /**
     * A Kernel with the protection composed in, as an application would.
     */
    private function send(string $method, string $uri = '/contact'): Response
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;

        $log = new Log(sys_get_temp_dir() . '/tether-csrf-test-logs');

        $kernel = new Kernel(
            $this->router,
            new Env(['APP_DEBUG' => 'false']),
            $log,
            [new VerifyCsrfToken($this->session, $log)],
        );

        $this->kernels[] = $kernel;

        return $kernel->run();
    }

    public function testAcceptsAWriteCarryingTheSessionToken(): void
    {
        $_POST = ['csrf_token' => $this->withToken()];

        $this->assertSame(200, $this->send('POST')->status());
    }

    /**
     * A write with no token at all used to raise "Undefined array key" and then
     * a TypeError, which surfaced as a 500 rather than a rejection.
     */
    public function testRejectsAWriteWithNoTokenAtAll(): void
    {
        $this->withToken();
        $_POST = [];

        $this->assertSame(403, $this->send('POST')->status());
    }

    public function testRejectsAWriteCarryingTheWrongToken(): void
    {
        $this->withToken();
        $_POST = ['csrf_token' => 'not-the-token'];

        $this->assertSame(403, $this->send('POST')->status());
    }

    /**
     * A request against a session that never had a token must be rejected
     * rather than crash its way past validation. The middleware issues one on
     * the way through, so the token it is compared against is never the one the
     * request presented.
     */
    public function testRejectsAWriteWhenTheSessionHadNoToken(): void
    {
        $_POST = ['csrf_token' => 'anything'];

        $this->assertSame(403, $this->send('POST')->status());
    }

    public function testTheRejectionRendersTheForbiddenPage(): void
    {
        $this->withToken();
        $_POST = [];

        $body = $this->send('POST')->body();

        $this->assertStringContainsString('403 Forbidden', $body);
        $this->assertStringContainsString('You are not allowed to access this resource.', $body);
    }

    /** @return list<list<string>> */
    public static function writeMethodProvider(): array
    {
        return [['POST'], ['PUT'], ['PATCH'], ['DELETE']];
    }

    #[DataProvider('writeMethodProvider')]
    public function testEveryWriteMethodIsProtected(string $method): void
    {
        $this->withToken();
        $_POST = [];

        $this->assertSame(403, $this->send($method)->status());
    }

    /**
     * PHP only fills $_POST for POST bodies, so a token could previously never
     * be presented on PUT, PATCH or DELETE — those methods were unusable.
     */
    public function testAcceptsATokenFromTheHeaderOnMethodsWithoutFormBodies(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $this->withToken();
        $_POST = [];

        $this->assertSame(200, $this->send('PUT')->status());
    }

    public function testRejectsAWrongTokenInTheHeader(): void
    {
        $this->withToken();
        $_POST = [];
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'not-the-token';

        $this->assertSame(403, $this->send('DELETE')->status());
    }

    public function testReadsAreNotChallenged(): void
    {
        $this->withToken();
        $_POST = [];

        $this->assertSame(200, $this->send('GET')->status());
    }

    /**
     * A preflight is not a write. Challenging everything that is not a GET
     * would refuse OPTIONS with a 403 rather than letting it resolve to no
     * route, which is what a CORS preflight would hit first.
     */
    public function testAPreflightIsNotChallenged(): void
    {
        $this->withToken();
        $_POST = [];

        $this->assertSame(404, $this->send('OPTIONS')->status());
    }

    /**
     * The point of the extraction: an application that does not compose the
     * middleware in has no CSRF check and no session — which is the right shape
     * for a token-authenticated API, and was impossible while a Request
     * validated its own token.
     */
    public function testAnApplicationWithoutTheMiddlewareIsNotChallenged(): void
    {
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/contact';

        $kernel = new Kernel(
            $this->router,
            new Env(['APP_DEBUG' => 'false']),
            new Log(sys_get_temp_dir() . '/tether-csrf-test-logs'),
        );

        $this->kernels[] = $kernel;

        $this->assertSame(200, $kernel->run()->status());
    }
}
