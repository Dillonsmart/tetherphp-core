<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Fixtures\app\Middleware;

use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Interfaces\MiddlewareInterface;
use TetherPHP\framework\Requests\Request;

/**
 * Work on the way out: the reason the contract takes $next rather than being
 * a before-only guard.
 */
final class AddsHeader implements MiddlewareInterface
{
    public function __construct(private readonly string $name, private readonly string $value)
    {
    }

    public function __invoke(Request $request, \Closure $next): Response
    {
        return $next($request)->withHeader($this->name, $this->value);
    }
}
