<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Fixtures\app\Actions;

use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Interfaces\ActionInterface;
use TetherPHP\framework\Requests\Request;

/**
 * Reports the whole request back as JSON.
 *
 * A CRUD route reads from three places — the path, the query string and the
 * body — and until they all arrived there was no fixture that could show which
 * of them was empty. This one asserts on all four at once.
 */
class Echoes implements ActionInterface
{
    public function __construct(private Request $request)
    {
    }

    public function __invoke(): Response
    {
        return Response::json([
            'method' => $this->request->method,
            'uri' => $this->request->uri,
            'params' => $this->request->params,
            'query' => $this->request->query,
            'payload' => $this->request->payload,
        ]);
    }
}
