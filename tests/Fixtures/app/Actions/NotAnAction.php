<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Fixtures\app\Actions;

use TetherPHP\framework\Requests\Request;

/** Deliberately does not implement ActionInterface. */
class NotAnAction
{
    public function __construct(private Request $request)
    {
    }
}
