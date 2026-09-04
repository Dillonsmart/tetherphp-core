<?php

declare(strict_types=1);

namespace TetherPHP\framework\Interfaces;

use TetherPHP\framework\Http\Response;

interface ActionInterface
{
    public function __invoke(): Response;
}
