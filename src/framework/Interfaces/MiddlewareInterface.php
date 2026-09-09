<?php

declare(strict_types=1);

namespace TetherPHP\framework\Interfaces;

use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Requests\Request;

/**
 * Something that runs around the rest of the pipeline.
 *
 * There was no way to run anything between a Request and an Action. Requiring
 * a login on ten routes meant the same guard pasted into ten Actions, and
 * anything a package might want to contribute — a session, a rate limiter, a
 * CORS header — had nowhere to attach. Principle 5 says extra functionality
 * composes in as packages, and until this existed nothing could.
 *
 * The contract is one method:
 *
 *     public function __invoke(Request $request, \Closure $next): Response
 *
 * Call `$next($request)` to continue and you get the Response from the rest of
 * the pipeline, which you may return, replace or add headers to. Return your
 * own Response without calling it and nothing further runs — that is how a
 * guard refuses a request, and it is the same shape whether the work happens
 * before, after or both.
 *
 * The alternative considered was a before-only guard returning `?Response`,
 * which is easier to read and cannot add a header to a response on the way
 * out. That would have meant a second concept the first time anything needed
 * to, so this takes the one that covers both. It is also the shape PHP
 * developers already recognise, which Agent Ready values over inventing a
 * local term.
 *
 * Middleware is given to the Kernel, and runs around routing — so it sees
 * requests that go on to 404, and can answer one before routing happens.
 */
interface MiddlewareInterface
{
    /**
     * @param \Closure(Request): Response $next the rest of the pipeline
     */
    public function __invoke(Request $request, \Closure $next): Response;
}
