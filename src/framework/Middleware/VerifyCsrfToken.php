<?php

declare(strict_types=1);

namespace TetherPHP\framework\Middleware;

use TetherPHP\framework\Exceptions\HttpForbiddenException;
use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Interfaces\MiddlewareInterface;
use TetherPHP\framework\Requests\Request;
use TetherPHP\framework\Sessions\CsrfToken;
use TetherPHP\framework\Modules\Log;
use TetherPHP\framework\Sessions\Session;

/**
 * Rejects a write that does not carry the session's CSRF token.
 *
 * This used to happen inside `Request::__construct()`, which meant the check
 * could not be turned off, replaced, or applied to some routes and not others —
 * and that an API-only application paid for a session it never wanted. The
 * Kernel constructed a Session and a CsrfToken whether or not anything used
 * them.
 *
 * It is a middleware now, and an application that wants CSRF protection says
 * so in `public/index.php`:
 *
 *     $session = new Session();
 *
 *     new Kernel($router, $env, $log, [
 *         new VerifyCsrfToken($session),
 *     ])->run()->send();
 *
 * An application that leaves it out has no sessions and no CSRF check, which is
 * the correct shape for a token-authenticated API and was previously impossible.
 *
 * Issuing the token and checking it are the same job, so this does both: a
 * session that has no token gets one, and every write must present it.
 */
final class VerifyCsrfToken implements MiddlewareInterface
{
    /**
     * The Log arrives here rather than being reached for. `Log::current()`
     * exists only to back the `logger()` helper; a framework class calling it
     * would be the ambient state Phase 3 removed, coming back in through a
     * class written after it.
     */
    public function __construct(
        private readonly Session $session,
        private readonly Log $log,
    ) {
    }

    /**
     * @throws HttpForbiddenException when the token is missing or wrong
     * @throws \Exception when a token cannot be generated
     */
    public function __invoke(Request $request, \Closure $next): Response
    {
        new CsrfToken($this->session);

        if ($request->isWrite() && !$this->presentedAValidToken($request)) {
            // a rejected write is a client error, not a server one
            $this->log->error("Rejected {$request->method} {$request->uri}: invalid CSRF token");

            throw new HttpForbiddenException();
        }

        return $next($request);
    }

    private function presentedAValidToken(Request $request): bool
    {
        $expected = $this->session->get('csrf_token');
        $presented = $this->presentedToken($request);

        // a request made against a session that never had a token generated is
        // rejected the same way a mismatched one is; it must not be able to
        // crash its way past validation
        return is_string($expected)
            && $presented !== null
            && hash_equals($expected, $presented);
    }

    /**
     * The token comes from the parsed body, or from the header.
     *
     * This read `$_POST` directly, which PHP only populates for a POST body —
     * so a PUT, PATCH or DELETE could never present a token in its body and
     * had to use the header. The Kernel parses every body now, whatever the
     * verb and whether it is a form or JSON, so the field works everywhere the
     * header does. Reading the Request rather than a superglobal also takes the
     * last piece of ambient state out of a framework class.
     *
     * The header stays: it is how fetch and XHR clients send it, and how a
     * multipart upload sends it without a hidden field.
     */
    private function presentedToken(Request $request): ?string
    {
        $token = $request->payload['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        return is_string($token) ? $token : null;
    }
}
