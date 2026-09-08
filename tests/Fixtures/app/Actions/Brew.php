<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Fixtures\app\Actions;

use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Interfaces\ActionInterface;
use TetherPHP\framework\Requests\Request;
use TetherPHP\Tests\Fixtures\app\Exceptions\TeapotException;

class Brew implements ActionInterface
{
    public function __construct(private Request $request)
    {
    }

    public function __invoke(): Response
    {
        throw new TeapotException();
    }
}
