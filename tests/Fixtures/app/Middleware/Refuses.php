<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Fixtures\app\Middleware;

use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Interfaces\MiddlewareInterface;
use TetherPHP\framework\Requests\Request;

/**
 * A guard that answers the request itself. Nothing after it runs — not the
 * rest of the middleware, not routing, not the Action.
 */
final class Refuses implements MiddlewareInterface
{
    public function __invoke(Request $request, \Closure $next): Response
    {
        return Response::html('refused', 401);
    }
}
