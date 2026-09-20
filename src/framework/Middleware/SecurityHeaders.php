<?php

declare(strict_types=1);

namespace TetherPHP\framework\Middleware;

use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Interfaces\MiddlewareInterface;
use TetherPHP\framework\Requests\Request;

/**
 * Adds the response headers every page should carry and none did.
 *
 * Three, and each closes one hole a browser leaves open by default:
 *
 *   X-Content-Type-Options: nosniff         a response is what its Content-Type
 *                                           says; the browser does not guess
 *   X-Frame-Options: DENY                   the page cannot be put in someone
 *                                           else's frame and clicked through
 *   Referrer-Policy: strict-origin-when-cross-origin
 *                                           a link away carries the origin, not
 *                                           the path — no /reset?token=… leaks
 *
 * A Content-Security-Policy is deliberately not here. There is no default one
 * that is both useful and safe for an application the framework has not seen;
 * the skeleton's own views carry inline styles that a strict policy blocks.
 * Pass one in when the application knows what it loads:
 *
 *     new SecurityHeaders([
 *         ...SecurityHeaders::DEFAULTS,
 *         'Content-Security-Policy' => "default-src 'self'",
 *     ]),
 *
 * Headers are added on the way out, so they land on error pages too — the one
 * class of response you least want a scanner to find bare. Composed like the
 * others, in `routes/middleware.php`; left out, nothing is sent.
 */
final class SecurityHeaders implements MiddlewareInterface
{
    /** @var array<string, string> */
    public const array DEFAULTS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    /**
     * @param array<string, string> $headers name => value; the list to send, not additions to the defaults
     */
    public function __construct(private readonly array $headers = self::DEFAULTS)
    {
    }

    public function __invoke(Request $request, \Closure $next): Response
    {
        $response = $next($request);

        foreach ($this->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
