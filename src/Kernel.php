<?php

declare(strict_types=1);

namespace TetherPHP;

use TetherPHP\framework\Http\Response;
use TetherPHP\framework\Interfaces\ActionInterface;
use TetherPHP\framework\Modules\Env;
use TetherPHP\framework\Modules\Log;
use TetherPHP\framework\Requests\Request;
use TetherPHP\framework\Routing\Route;
use TetherPHP\framework\Sessions\CsrfToken;
use TetherPHP\framework\Sessions\Session;

class Kernel
{
    protected Request $request;

    protected string $versionName = "0.4 alpha";

    protected float $versionNumber = 0.4;

    protected Session $session;

    /**
     * @throws \Exception
     */
    public function __construct(protected Router $router)
    {
        Env::getInstance();

        if (!defined('VERSION_NAME')) {
            define('VERSION_NAME', $this->versionName);
        }

        if (!defined('VERSION')) {
            define('VERSION', $this->versionNumber);
        }

        $this->setErrorHandler();

        $this->session = new Session();

        new CsrfToken($this->session); // ensure CSRF token is generated
    }

    /**
     * Resolves the request to a response.
     *
     * Every path through this method returns a Response — a match, a miss, a
     * rejected write, a misconfigured route. Nothing is echoed and nothing
     * exits, which is what makes the whole pipeline testable.
     */
    public function run(): Response
    {
        try {
            $this->request = new Request(
                $this->session,
                $this->requestMethod(),
                $this->requestPath(),
                microtime(true),
            );
        } catch (\Exception $e) {
            // A rejected write is a client error, not a server one.
            Log::error('Rejected request: ' . $e->getMessage());

            return $this->errorResponse(403);
        }

        $route = $this->router->routeAction($this->request);

        if (!$route->matched) {
            return $this->errorResponse(404);
        }

        $this->request->params = $route->params;
        $this->request->payload = $this->payload();

        if ($route->isView()) {
            return Response::html($this->renderView($route->action));
        }

        return $this->invoke($route);
    }

    private function invoke(Route $route): Response
    {
        if (!class_exists($route->action)) {
            Log::error("Route points at {$route->action}, which does not exist.");

            return $this->errorResponse(500);
        }

        $action = new $route->action($this->request);

        if (!$action instanceof ActionInterface) {
            Log::error(sprintf(
                '%s must implement %s to be routable.',
                $route->action,
                ActionInterface::class,
            ));

            return $this->errorResponse(500);
        }

        return $action();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        // CONTENT_TYPE is absent on any request without a body, which is most of them
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (is_string($contentType) && str_contains($contentType, 'application/json')) {
            $decoded = json_decode(file_get_contents('php://input') ?: '', true);

            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }

    private function renderView(string $view): string
    {
        $file = views_dir() . str_replace('.', '/', $view) . '.php';

        if (!file_exists($file)) {
            Log::error("View route points at {$view}, which does not exist.");

            return $this->errorBody(500);
        }

        ob_start();
        include $file;

        return ob_get_clean() ?: '';
    }

    private function errorResponse(int $status): Response
    {
        return Response::html($this->errorBody($status), $status);
    }

    /**
     * The application's error view wins; the framework ships fallbacks so an
     * application that has not written one still gets a page rather than an
     * empty body from a failed include.
     */
    private function errorBody(int $status): string
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

    private function setErrorHandler(): void
    {
        if (env('APP_DEBUG') === 'true') {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            Log::error("Error [$errno]: $errstr in $errfile on line $errline");

            // Notices, warnings and deprecations are logged, not fatal. Replacing
            // the page with a 500 because something was deprecated hides the real
            // response and tells the user nothing.
            $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];

            if (!in_array($errno, $fatal, true)) {
                return true;
            }

            $this->renderFatalError();
        });

        set_exception_handler(function ($exception) {
            Log::error("Uncaught Exception: " . $exception->getMessage());
            Log::error("Uncaught Exception: " . $exception->getTraceAsString());

            $this->renderFatalError();
        });
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
