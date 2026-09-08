<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Fixtures\app\Actions;

use TetherPHP\framework\Exceptions\HttpNotFoundException;
use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Interfaces\ActionInterface;
use TetherPHP\framework\Requests\Request;

class ThrowsNotFound implements ActionInterface
{
    public function __construct(private Request $request)
    {
    }

    public function __invoke(): Response
    {
        throw new HttpNotFoundException('No user exists with that id.');
    }
}
