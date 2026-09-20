<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Sessions\Session;

/**
 * Constructing a Session must do nothing.
 *
 * It used to call session_start() in its constructor, so merely naming one
 * issued a cookie and wrote a file whether or not anything read or wrote a
 * value. That was invisible while the Kernel built the Session itself, and
 * became load-bearing the moment the console needed to build an application's
 * middleware list to report it: `tether routes` must not start a session.
 */
class SessionTest extends TestCase
{
    public function testConstructingASessionDoesNotStartOne(): void
    {
        $this->assertFalse(new Session()->hasStarted());
    }

    public function testUsingASessionStartsIt(): void
    {
        $session = new Session();
        $session->set('probe', 'value');

        $this->assertTrue($session->hasStarted());
        $this->assertSame('value', $session->get('probe'));
    }

    /**
     * Behind a TLS-terminating proxy PHP sees plain HTTP, so the Secure flag
     * depends on X-Forwarded-Proto — and only when the application says the
     * header can be believed, because any client can send it.
     */
    public function testTheForwardedProtoHeaderIsBelievedOnlyWhenTrusted(): void
    {
        $_SERVER['HTTPS'] = '';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

        $this->assertFalse($this->secureFlagOf(new Session()));
        $this->assertTrue($this->secureFlagOf(new Session(trustForwardedProto: true)));

        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
        $this->assertFalse($this->secureFlagOf(new Session(trustForwardedProto: true)));

        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
    }

    public function testSessionsStartInStrictMode(): void
    {
        new Session()->set('probe', 'value');

        $this->assertSame('1', ini_get('session.use_strict_mode'));
    }

    public function testRegeneratingTheIdDropsTheCsrfToken(): void
    {
        $session = new Session();
        $session->set('csrf_token', 'issued-before-login');

        $session->regenerateId();

        $this->assertNull($session->get('csrf_token'));
    }

    /**
     * The cookie params are what session_start() will use; reading them back
     * after a start is how to see what the flag was set to under the CLI,
     * where no cookie is actually sent.
     */
    private function secureFlagOf(Session $session): bool
    {
        $session->set('probe', 'value');
        $secure = (bool) session_get_cookie_params()['secure'];
        $session->destroy();

        return $secure;
    }

    public function testReadingAlsoStartsIt(): void
    {
        $session = new Session();

        $this->assertNull($session->get('never-set-anywhere'));
        $this->assertTrue($session->hasStarted());
    }
}
