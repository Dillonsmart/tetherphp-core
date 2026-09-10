<?php

declare(strict_types=1);

namespace TetherPHP;

use TetherPHP\framework\Exceptions\HttpException;
use TetherPHP\framework\Exceptions\HttpInternalServerErrorException;
use TetherPHP\framework\Exceptions\HttpNotFoundException;
use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Interfaces\ActionInterface;
use TetherPHP\framework\Interfaces\MiddlewareInterface;
use TetherPHP\framework\Modules\Env;
use TetherPHP\framework\Modules\Log;
use TetherPHP\framework\Requests\Request;
use TetherPHP\framework\Routing\Route;

class Kernel
{
    protected Request $request;

    /** The handlers this Kernel installed, kept so they can be taken back off. */
    private ?\Closure $errorHandler = null;

    private ?\Closure $exceptionHandler = null;

    /**
     * The environment and the log arrive as arguments rather than being found.
     *
     * Both used to be reached statically from inside the request — `Env` built
     * itself from project_root() on first use, `Log` wrote to storage_dir()
     * with no way to say otherwise. What the Kernel needs to do its job now
     * comes through its constructor, and `public/index.php` is where a reader
     * can see which environment file and which log directory are in play.
     *
     * Installing them for the `env()` and `logger()` helpers is the one side
     * effect here, and it is deliberate: application code in a view or a Domain
     * should not have to thread an object through to read a setting. Those two
     * functions are the only readers of `Env::current()` and `Log::current()`.
     *
     * Middleware is the seam everything else composes onto. It arrives as a
     * list rather than being discovered, so the order things run in is the
     * order they are written in `public/index.php` and nowhere else.
     *
     * The Kernel used to construct a Session and a CsrfToken here whether or
     * not anything used them, and a Request validated its own CSRF token. An
     * application that wants either now composes `VerifyCsrfToken` in, and one
     * that does not — a token-authenticated API — boots without a session at
     * all, which was previously impossible.
     *
     * @param list<MiddlewareInterface> $middleware
     *
     * @throws \Exception
     */
    public function __construct(
        protected Router $router,
        protected Env $env,
        protected Log $log,
        protected array $middleware = [],
    ) {
        Env::use($this->env);
        Log::use($this->log);

        $this->setErrorHandler();
    }

    /**
     * Resolves the request to a response.
     *
     * Every path through this method returns a Response — a match, a miss, a
     * rejected write, a misconfigured route. Error paths throw inside
     * handle() and are turned back into Responses here, so ending a request
     * early has one obvious way: throw an HttpException. Nothing is echoed
     * and nothing exits, which is what makes the whole pipeline testable.
     */
    public function run(): Response
    {
        try {
            return $this->handle();
        } catch (HttpException $e) {
            return $this->exceptionResponse($e);
        } catch (\Throwable $e) {
            $this->log->error('Uncaught: ' . $e->getMessage());
            $this->log->error($e->getTraceAsString());

            return $this->exceptionResponse(new HttpInternalServerErrorException());
        }
    }

    /**
     * The pipeline proper: Request → Middleware → Route → Action → Response.
     *
     * Anything that goes wrong on the way throws — an HttpException for the
     * errors that are part of the HTTP conversation, anything else for bugs.
     * run() is the only place either is caught, which is what lets a
     * middleware throw an HttpException to refuse a request instead of
     * building the error Response itself.
     *
     * @throws HttpException
     */
    private function handle(): Response
    {
        $this->request = new Request(
            $this->requestMethod(),
            $this->requestPath(),
            microtime(true),
            $this->payload(),
            $this->requestQuery(),
        );

        return $this->through($this->respond(...))($this->request);
    }

    /**
     * Routing and dispatch, with the HTTP errors already turned into Responses.
     *
     * The conversion happens *inside* the middleware rather than in run(), so
     * a 404 comes back out through every layer as an ordinary Response. If it
     * were thrown past them, middleware that adds something on the way out —
     * a security header, a timing measurement — would silently not apply to
     * error pages, which is the one class of response you least want to miss.
     *
     * run() still catches: a middleware that throws before calling $next has
     * no inner pipeline to be caught by.
     */
    private function respond(Request $request): Response
    {
        try {
            return $this->dispatch($request);
        } catch (HttpException $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Wraps the dispatch in the middleware, outermost first.
     *
     * Built back to front so that the first middleware in the list is the
     * outermost layer — the first to see a request and the last to see a
     * response, which is the order anyone writing the list expects.
     *
     * @param \Closure(Request): Response $dispatch
     *
     * @return \Closure(Request): Response
     */
    private function through(\Closure $dispatch): \Closure
    {
        $next = $dispatch;

        foreach (array_reverse($this->middleware) as $middleware) {
            // both are captured by value here, so each layer keeps the one
            // that was built before it rather than the final $next
            $next = static fn (Request $request): Response => $middleware($request, $next);
        }

        return $next;
    }

    /**
     * Route and invoke: what runs once every middleware has called $next.
     *
     * @throws HttpException
     */
    private function dispatch(Request $request): Response
    {
        $route = $this->router->routeAction($request);

        if (!$route->matched) {
            throw new HttpNotFoundException();
        }

        $request->params = $route->params;

        if ($route->isView()) {
            return Response::html($this->renderView($route->action));
        }

        return $this->invoke($route);
    }

    private function exceptionResponse(HttpException $e): Response
    {
        $response = $this->errorResponse($e->status(), $e->title(), $e->description());

        foreach ($e->headers() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * @throws HttpInternalServerErrorException when the route is misconfigured
     */
    private function invoke(Route $route): Response
    {
        if (!class_exists($route->action)) {
            $this->log->error("Route points at {$route->action}, which does not exist.");

            throw new HttpInternalServerErrorException('The route points at an action that does not exist.');
        }

        $action = new $route->action($this->request);

        if (!$action instanceof ActionInterface) {
            $this->log->error(sprintf(
                '%s must implement %s to be routable.',
                $route->action,
                ActionInterface::class,
            ));

            throw new HttpInternalServerErrorException('The route points at an action that is not routable.');
        }

        return $action();
    }

    /**
     * The request body, parsed.
     *
     * This used to be `$_POST` with a JSON special case, which quietly limited
     * the framework to the C and part of the R of CRUD: PHP populates `$_POST`
     * for a POST body and for nothing else, so a form-encoded PUT, PATCH or
     * DELETE — an update or a delete sent by anything other than a JSON client
     * — arrived as an empty array. The route matched, the Action ran, and the
     * fields were simply not there.
     *
     * `$_POST` is still preferred where PHP has filled it, because it is the
     * only thing that can read a multipart body: `php://input` is empty for
     * multipart/form-data, so parsing by hand would lose file uploads.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        // CONTENT_TYPE is absent on any request without a body, which is most of them
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $contentType = is_string($contentType) ? $contentType : '';

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($this->body(), true);

            return is_array($decoded) ? $decoded : [];
        }

        if ($_POST !== []) {
            return $_POST;
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($this->body(), $parsed);

            return $this->withStringKeys($parsed);
        }

        return [];
    }

    /**
     * The raw request body.
     *
     * Read through one method so the two parsers above cannot disagree about
     * what "the body" is, and protected because it is the one input a test
     * cannot arrange: $_SERVER and $_POST are globals a test can set, and
     * `php://input` under the CLI is always empty. A test that needs to send a
     * form-encoded PUT overrides this; nothing else should.
     */
    protected function body(): string
    {
        return file_get_contents('php://input') ?: '';
    }

    /**
     * @throws HttpInternalServerErrorException when the view route is misconfigured
     */
    private function renderView(string $view): string
    {
        $file = views_dir() . str_replace('.', '/', $view) . '.php';

        if (!file_exists($file)) {
            $this->log->error("View route points at {$view}, which does not exist.");

            throw new HttpInternalServerErrorException('The route points at a view that does not exist.');
        }

        ob_start();
        include $file;

        return ob_get_clean() ?: '';
    }

    private function errorResponse(int $status, string $title = '', string $description = ''): Response
    {
        return Response::html($this->errorBody($status, $title, $description), $status);
    }

    /**
     * The application's error view wins; the framework ships fallbacks so an
     * application that has not written one still gets a page rather than an
     * empty body from a failed include.
     *
     * The view is included with $status, $title and $description in scope.
     * The framework's fallbacks render all three; an application view may
     * use as many of them as it likes.
     */
    private function errorBody(int $status, string $title = '', string $description = ''): string
    {
        $view = views_dir() . "errors/{$status}.php";

        if (!file_exists($view)) {
            $view = core_views() . "errors/{$status}.php";
        }

        if (!file_exists($view)) {
            return "<h1>{$status}</h1>";
        }

        ob_start();
        include $view;

        return ob_get_clean() ?: '';
    }

    private function requestMethod(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        return is_string($method) ? $method : 'GET';
    }

    /**
     * The path alone, without the query string.
     *
     * REQUEST_URI carries the query string, so routing on it raw meant any URL
     * with parameters — pagination, a UTM tag, a filter — failed to match.
     */
    private function requestPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        if (!is_string($uri) || $uri === '') {
            return '/';
        }

        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    /**
     * The query string, parsed.
     *
     * requestPath() has always split this off so that `/posts?page=2` could
     * match the route `/posts`, and then thrown it away — which is why the
     * roadmap lists the query string as something "no application can currently
     * read". Both halves of REQUEST_URI now reach the Request.
     *
     * Parsed from REQUEST_URI rather than read from `$_GET` so that the path
     * and the query come from one source. A test that sets REQUEST_URI gets a
     * request that is consistent with itself.
     *
     * @return array<string, mixed>
     */
    private function requestQuery(): array
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        if (!is_string($uri) || $uri === '') {
            return [];
        }

        $queryString = parse_url($uri, PHP_URL_QUERY);

        if (!is_string($queryString) || $queryString === '') {
            return [];
        }

        parse_str($queryString, $query);

        return $this->withStringKeys($query);
    }

    /**
     * parse_str() hands back an int key for a field named with a number —
     * `0=yes` — where a Request promises `array<string, mixed>`.
     *
     * The keys are cast rather than the promise widened. A field name is a
     * string in the request that carried it, and `$request->payload['12']`
     * missing a value that `$request->payload[12]` would have found is exactly
     * the kind of thing nobody debugs twice.
     *
     * @param array<array-key, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function withStringKeys(array $values): array
    {
        $normalised = [];

        foreach ($values as $key => $value) {
            $normalised[(string) $key] = $value;
        }

        return $normalised;
    }

    /**
     * Puts back whatever error and exception handlers were in place before this
     * Kernel installed its own.
     *
     * Installing handlers in the constructor and never offering a way out meant
     * each Kernel left a pair behind — invisible in a web request that ends, a
     * leak anywhere the process continues. PHP 8.5's get_error_handler() and
     * get_exception_handler() make it checkable: only restore if ours is still
     * the one on top, so a handler installed after this Kernel is not clobbered.
     */
    public function restoreErrorHandlers(): void
    {
        if ($this->errorHandler !== null && get_error_handler() === $this->errorHandler) {
            restore_error_handler();
        }

        if ($this->exceptionHandler !== null && get_exception_handler() === $this->exceptionHandler) {
            restore_exception_handler();
        }

        $this->errorHandler = null;
        $this->exceptionHandler = null;
    }

    private function setErrorHandler(): void
    {
        if ($this->env->get('APP_DEBUG') === 'true') {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }

        $this->errorHandler = function ($errno, $errstr, $errfile, $errline) {
            $this->log->error("Error [$errno]: $errstr in $errfile on line $errline");

            // Notices, warnings and deprecations are logged, not fatal. Replacing
            // the page with a 500 because something was deprecated hides the real
            // response and tells the user nothing.
            $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];

            if (!in_array($errno, $fatal, true)) {
                return true;
            }

            $this->renderFatalError();
        };

        $this->exceptionHandler = function ($exception) {
            $this->log->error("Uncaught Exception: " . $exception->getMessage());
            $this->log->error("Uncaught Exception: " . $exception->getTraceAsString());

            $this->renderFatalError();
        };

        set_error_handler($this->errorHandler);
        set_exception_handler($this->exceptionHandler);
    }

    /**
     * Last resort for an error that escaped the pipeline. Guarded against
     * re-entry so an error inside the error view cannot recurse.
     */
    private function renderFatalError(): never
    {
        static $rendering = false;

        if (!$rendering) {
            $rendering = true;

            $this->errorResponse(500)->send();
        }

        exit(1);
    }
}
