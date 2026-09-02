<?php

declare(strict_types=1);

namespace TetherPHP\framework\Interfaces;

interface ResponderInterface
{
    /**
     * Render a view with the given data.
     */
    public function view(string $viewName, array $data = []): string;

    /**
     * Return a JSON response.
     */
    public function json(array $data, int $statusCode = 200): string;
}
