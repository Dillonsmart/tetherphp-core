<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Fixtures\app\Actions;

use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Interfaces\ActionInterface;
use TetherPHP\framework\Requests\Request;

class Greet implements ActionInterface
{
    public function __construct(private Request $request)
    {
    }

    public function __invoke(): Response
    {
        // proves route parameters reach the action rather than being dropped
        $name = $this->request->params['name'] ?? 'world';

        return Response::html("hello {$name}");
    }
}
