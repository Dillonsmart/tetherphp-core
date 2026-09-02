<?php

declare(strict_types=1);

namespace TetherPHP\framework\DTOs;

class RouteDTO
{
    /** @var class-string|string the action class name, or a view name for view routes */
    public string $action;
    public string $type;
    /** @var array<string, string> */
    public array $params;
}