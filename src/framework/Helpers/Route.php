<?php

declare(strict_types=1);

namespace TetherPHP\framework\Helpers;

class Route
{
    public static function isActive(string $route): bool
    {
        $currentRoute = $_SERVER['REQUEST_URI'];

        return $currentRoute === $route;
    }
}