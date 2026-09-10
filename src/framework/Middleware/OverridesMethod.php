<?php

declare(strict_types=1);

namespace TetherPHP\framework\Middleware;

use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Interfaces\MiddlewareInterface;
use TetherPHP\framework\Requests\Request;

/**
 * Lets an HTML form ask for PUT, PATCH or DELETE.
 *
 * The Router has been able to register those three verbs since v0.4.0 and a
 * browser has never been able to reach them: a form element sends GET or POST
 * and nothing else. So the U and the D of CRUD worked for a JSON client and
 * were unroutable from a page, and the only way out was to route updates and
 * deletes through POST — which is the framework quietly telling an application
 * that half of its own routing API is decorative.
 *
 * A form says which verb it meant:
 *
 *     <form method="post" action="/posts/12">
 *         <input type="hidden" name="_method" value="PUT">
 *
 * This is the one piece of behaviour in the framework triggered by a magic
 * field name, which is why it is a middleware rather than something the Kernel
 * does. An application that wants it composes it in `routes/middleware.php`,
 * where a reader can see it, and `tether routes` and `tether explain` both
 * report it as part of what a request passes through. Leave it out and
 * `_method` is an ordinary form field with no meaning.
 *
 * Compose it above VerifyCsrfToken. Both orders are safe — the request is a
 * POST before the override and a PUT after it, and Request::isWrite() is true
 * either way — but the CSRF middleware logs the verb it rejected, and the verb
 * the application asked for is the more useful one to read at 3am.
 */
final class OverridesMethod implements MiddlewareInterface
{
    /**
     * The field a form declares its real verb in.
     *
     * Laravel and Symfony both spell it `_method`, and a convention borrowed
     * is one fewer thing to look up.
     */
    private const string FIELD = '_method';

    /**
     * Only these three. Overriding into GET would turn a form submission into
     * something the browser thinks it can repeat, and overriding into POST is
     * what the request already is.
     *
     * @var list<string>
     */
    private const array OVERRIDABLE = ['PUT', 'PATCH', 'DELETE'];

    public function __invoke(Request $request, \Closure $next): Response
    {
        // only a POST is upgraded: a GET carrying ?_method=DELETE is a link,
        // and following one must not delete anything
        if ($request->method === 'POST') {
            $override = $request->payload[self::FIELD] ?? null;

            if (is_string($override) && in_array(strtoupper($override), self::OVERRIDABLE, true)) {
                $request->method = $override;
            }
        }

        return $next($request);
    }
}
