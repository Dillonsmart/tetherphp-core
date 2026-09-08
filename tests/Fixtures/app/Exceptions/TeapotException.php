<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Fixtures\app\Exceptions;

use TetherPHP\framework\Exceptions\HttpException;

/** Stands in for an application-defined status that requires headers. */
class TeapotException extends HttpException
{
    public function __construct()
    {
        parent::__construct(418, 'I Am A Teapot', 'This server refuses to brew coffee.');
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return ['X-Beverage' => 'tea'];
    }
}
