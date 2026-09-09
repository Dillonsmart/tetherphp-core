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

    public function testReadingAlsoStartsIt(): void
    {
        $session = new Session();

        $this->assertNull($session->get('never-set-anywhere'));
        $this->assertTrue($session->hasStarted());
    }
}
