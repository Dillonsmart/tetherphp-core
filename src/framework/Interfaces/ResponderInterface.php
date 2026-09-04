<?php

declare(strict_types=1);

namespace TetherPHP\framework\Interfaces;

use TetherPHP\framework\Http\Response;

interface ResponderInterface
{
    /**
     * Render a view into a response.
     *
     * @param array<string, mixed> $data
     */
    public function view(string $viewName, array $data = [], int $status = 200): Response;

    /**
     * Return a JSON response.
     *
     * @param array<string, mixed> $data
     */
    public function json(array $data, int $statusCode = 200): Response;
}
