<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Fixtures\app\Actions;

use TetherPHP\framework\Requests\Request;

class Greet
{
    public function __construct(private Request $request)
    {
    }

    public function __invoke(): string
    {
        return 'hello from the fixture app';
    }
}
