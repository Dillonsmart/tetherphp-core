<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Requests\Request;
use TetherPHP\framework\Sessions\CsrfToken;
use TetherPHP\framework\Sessions\Session;

class CsrfProtectionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
        $this->session = new Session();
    }

    private function withToken(): string
    {
        new CsrfToken($this->session);

        $token = $this->session->get('csrf_token');
        $this->assertIsString($token);

        return $token;
    }

    public function testAcceptsAWriteCarryingTheSessionToken(): void
    {
        $_POST = ['csrf_token' => $this->withToken()];

        $request = new Request($this->session, 'POST', '/contact', microtime(true));

        $this->assertSame('POST', $request->method);
    }

    /**
     * A write with no token at all used to raise "Undefined array key" and then a
     * TypeError, which surfaced as a 500 rather than a rejection.
     */
    public function testRejectsAWriteWithNoTokenAtAll(): void
    {
        $this->withToken();
        $_POST = [];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid CSRF token');

        new Request($this->session, 'POST', '/contact', microtime(true));
    }

    public function testRejectsAWriteCarryingTheWrongToken(): void
    {
        $this->withToken();
        $_POST = ['csrf_token' => 'not-the-token'];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid CSRF token');

        new Request($this->session, 'POST', '/contact', microtime(true));
    }

    /**
     * No token has been generated for the session, so there is nothing to compare
     * against. This must reject rather than crash its way past validation.
     */
    public function testRejectsAWriteWhenTheSessionHasNoToken(): void
    {
        $_POST = ['csrf_token' => 'anything'];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid CSRF token');

        new Request($this->session, 'POST', '/contact', microtime(true));
    }

    /**
     * Constructing a read on a tokenless session used to fail assigning null to a
     * property typed string.
     */
    public function testAReadOnATokenlessSessionIsNotAnError(): void
    {
        $request = new Request($this->session, 'GET', '/', microtime(true));

        $this->assertSame('/', $request->uri);
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

        $this->expectException(\Exception::class);

        new Request($this->session, $method, '/contact', microtime(true));
    }

    /**
     * PHP only fills $_POST for POST bodies, so a token could previously never
     * be presented on PUT, PATCH or DELETE — those methods were unusable.
     */
    public function testAcceptsATokenFromTheHeaderOnMethodsWithoutFormBodies(): void
    {
        $token = $this->withToken();
        $_POST = [];
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;

        $request = new Request($this->session, 'PUT', '/thing/1', microtime(true));

        $this->assertSame('PUT', $request->method);

        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    public function testRejectsAWrongTokenInTheHeader(): void
    {
        $this->withToken();
        $_POST = [];
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'not-the-token';

        try {
            $this->expectException(\Exception::class);
            new Request($this->session, 'DELETE', '/thing/1', microtime(true));
        } finally {
            unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        }
    }

    public function testReadsAreNotChallenged(): void
    {
        $this->withToken();
        $_POST = [];

        $request = new Request($this->session, 'GET', '/', microtime(true));

        $this->assertSame('GET', $request->method);
    }
}
