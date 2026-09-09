<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Fixtures\app\Middleware;

use TetherPHP\framework\Exceptions\HttpForbiddenException;
use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Interfaces\MiddlewareInterface;
use TetherPHP\framework\Requests\Request;

/**
 * Refuses by throwing, which is the framework's one obvious way to end a
 * request early. run() catches it exactly as it does from an Action.
 */
final class Throws implements MiddlewareInterface
{
    public function __invoke(Request $request, \Closure $next): Response
    {
        throw new HttpForbiddenException();
    }
}
