<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Fixtures\app\Actions;

use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Interfaces\ActionInterface;
use TetherPHP\framework\Requests\Request;
use TetherPHP\Tests\Fixtures\app\Services;

/**
 * Takes the services the Kernel offers, and answers with what was in them.
 */
class Signs implements ActionInterface
{
    public function __construct(private Request $request, private Services $services)
    {
    }

    public function __invoke(): Response
    {
        return Response::html("signed by {$this->services->signature}");
    }
}
