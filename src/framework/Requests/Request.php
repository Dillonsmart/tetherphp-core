<?php

declare(strict_types=1);

namespace TetherPHP\framework\Requests;

use TetherPHP\framework\Interfaces\RequestInterface;

/**
 * What was asked for.
 *
 * A Request used to take a Session and validate a CSRF token in its own
 * constructor, so constructing one could throw, every test that needed a
 * Request needed a session, and an API-only application got session-based CSRF
 * whether it wanted it or not. Worse, it welded the framework's one security
 * check to a class whose job is to describe a request — there was no way to
 * turn it off, replace it, or apply it to only some routes.
 *
 * That check is now `Middleware\VerifyCsrfToken`, which an application composes
 * in. This class describes the request and nothing else.
 *
 * It describes all of it. A request has three sources of input — the path, the
 * query string and the body — and until CRUD needed them there were only two,
 * neither complete: the query string was parsed off and thrown away by the
 * Kernel, and the body was read from `$_POST`, which PHP fills for a POST and
 * nothing else. An update sent as a form-encoded PUT arrived empty.
 *
 * All three are plain public arrays, read with `??` for a key that may be
 * absent:
 *
 *     $id    = $request->params['id'] ?? '';        // from the path
 *     $page  = $request->query['page'] ?? '1';      // from the query string
 *     $title = $request->payload['title'] ?? '';    // from the body
 *
 * There is deliberately no `input()` accessor over the top of them. It would be
 * a second way to read the same value, and array access with a default already
 * says where the value came from — which is the question an unfamiliar reader
 * is actually asking.
 */
class Request implements RequestInterface
{
    public string $method {
        get {
            return $this->method;
        }
        set {
            $this->method = strtoupper($value);
        }
    }

    /**
     * The path, exactly as it was asked for.
     *
     * This used to be lowercased by a property hook so that routing could
     * compare it directly against the route table. It made routing
     * case-insensitive by destroying the URI, and everything downstream paid:
     * a parameter captured from `/posts/My-Slug` arrived as `my-slug`, so no
     * application could route a slug or a UUID. Case-insensitive matching is
     * the Router's job, and it does it by comparing case-insensitively rather
     * than by rewriting what the client sent.
     */
    public string $uri = '';

    /**
     * The parsed request body.
     *
     * Populated by the Kernel before any middleware runs, so a middleware can
     * read it — `Middleware\OverridesMethod` has to, and it used to be assigned
     * during dispatch, after every middleware had already been and gone.
     * A typed property with no default also meant reading it early was not an
     * empty array but a fatal "must not be accessed before initialization".
     *
     * @var array<string, mixed>
     */
    public array $payload = [];

    /**
     * The parsed query string.
     *
     * The Kernel has always split this off the URI so that `/posts?page=2`
     * could match `/posts`, and then dropped it on the floor. An index page —
     * the R of CRUD — cannot paginate, filter or search without it.
     *
     * @var array<string, mixed>
     */
    public array $query = [];

    /**
     * Parameters captured from a dynamic route.
     *
     * The router captured these all along and the Kernel dropped them, so every
     * application had to re-parse the URI inside its own Actions. They arrive
     * with the request now.
     *
     * @var array<string, string>
     */
    public array $params = [];

    public float|string $startTime;

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $query
     */
    public function __construct(
        string $method = '',
        string $uri = '',
        float|string $startTime = '',
        array $payload = [],
        array $query = [],
    ) {
        $this->method = $method;
        $this->uri = $uri;
        $this->payload = $payload;
        $this->query = $query;
        $this->startTime = $startTime ?: microtime(true);
    }

    /**
     * Whether this request is one that changes something.
     *
     * Named rather than inverted — "not GET and not HEAD" would also challenge
     * OPTIONS, so a CORS preflight would be refused with a 403 instead of
     * resolving to no route. Only these four can be registered as writes, and
     * anything else 404s before reaching an Action.
     */
    public function isWrite(): bool
    {
        return in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }
}
