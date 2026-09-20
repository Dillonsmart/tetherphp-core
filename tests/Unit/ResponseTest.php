<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Http\Response;

class ResponseTest extends TestCase
{
    /**
     * PHP's header() refuses a value carrying a newline, but by warning and
     * dropping the header. A redirect built from a target with %0d%0a in it
     * went out as a 303 with no Location and one line in the log. It is an
     * exception now, so the failure has a reason attached to it.
     */
    public function testAHeaderCarryingANewlineIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Response::redirect("/next\r\nSet-Cookie: session=stolen");
    }

    public function testAHeaderNameCarryingANewlineIsRefusedToo(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Response::html('')->withHeader("X-Thing\n", 'value');
    }

    public function testAnOrdinaryRedirectIsUnaffected(): void
    {
        $response = Response::redirect('/posts/12', 303);

        $this->assertSame(303, $response->status());
        $this->assertSame('/posts/12', $response->headers()['Location']);
    }
}
