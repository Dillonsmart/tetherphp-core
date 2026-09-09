<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Fixtures\app\Middleware;

use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Interfaces\MiddlewareInterface;
use TetherPHP\framework\Requests\Request;

/**
 * Notes when it was entered and left, so a test can assert the layers nest in
 * the order the list is written rather than merely that they all ran.
 */
final class Records implements MiddlewareInterface
{
    /** @var list<string> */
    public static array $log = [];

    public function __construct(private readonly string $name)
    {
    }

    public function __invoke(Request $request, \Closure $next): Response
    {
        self::$log[] = "enter {$this->name}";

        $response = $next($request);

        self::$log[] = "leave {$this->name}";

        return $response;
    }
}
