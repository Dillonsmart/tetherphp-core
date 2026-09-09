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
    public string $uri {
        get {
            return $this->uri;
        }
        set {
            $this->uri = strtolower($value);
        }
    }

    /** @var array<string, mixed> */
    public array $payload {
        get {
            return $this->payload;
        }
        set {
            $this->payload = $value;
        }
    }

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

    public function __construct(string $method = '', string $uri = '', float|string $startTime = '')
    {
        $this->method = $method;
        $this->uri = $uri;
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
