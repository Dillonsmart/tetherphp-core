<?php

declare(strict_types=1);

namespace TetherPHP;

use TetherPHP\framework\Modules\Env;
use TetherPHP\framework\Modules\Log;
use TetherPHP\framework\Requests\Request;
use TetherPHP\framework\Sessions\CsrfToken;
use TetherPHP\framework\Sessions\Session;

class Kernel
{

    protected Request $request;

    protected string $versionName = "0.1 alpha";

    protected float $versionNumber = 0.1;

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
     * @return string the response body
     *
     * @throws \Exception
     */
    public function run()
    {
        try {
            $this->request = new Request(
                $this->session,
                $this->requestMethod(),
                $this->requestPath(),
                microtime(true),
            );
        } catch (\Exception $e) {
            // A rejected write is a client error, not a server one. Uncaught, it
            // reached the exception handler and was reported as a 500.
            Log::error('Rejected request: ' . $e->getMessage());

            return $this->errorView(403);
        }

        $route = $this->router->routeAction($this->request);

        if (!isset($route->action)) {
            return $this->errorView(404);
        }

        if ($route->type === 'view') {
            ob_start();
            include(views_dir() . str_replace('.', '/', $route->action) . '.php');
            return ob_get_clean() ?: '';
        }

        if (!class_exists($route->action)) {
            Log::error("Route points at {$route->action}, which does not exist.");

            return $this->errorView(500);
        }

        // CONTENT_TYPE is absent on any request without a body, which is most of them
        if (($_SERVER['CONTENT_TYPE'] ?? '') === 'application/json') {
            $this->request->payload = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        } else {
            $this->request->payload = $_POST;
        }

        $invokeAction = new $route->action($this->request);

        if (!is_callable($invokeAction)) {
            Log::error("Action {$route->action} is not invokable — it needs an __invoke() method.");

            return $this->errorView(500);
        }

        return $invokeAction();
    }

    /**
     * Sets the status and returns the rendered error page.
     *
     * The application's own view wins; the framework ships fallbacks so an
     * application that has not written one still gets a page rather than an
     * empty body from a failed include. Previously these were echoed directly
     * and '' was returned, so run() did not actually return the response it
     * claims to.
     */
    private function errorView(int $status): string
    {
        if (!headers_sent()) {
            http_response_code($status);
        }

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
     * with parameters — pagination, a UTM tag, a filter — failed to match and
     * returned a 404.
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
     * Renders the 500 page and stops. Guarded against re-entry so that an error
     * inside the error view cannot recurse through the handler.
     */
    private function renderFatalError(): never
    {
        static $rendering = false;

        if (!$rendering) {
            $rendering = true;

            // exit() sets a process status, not an HTTP one — the response code has
            // to be set explicitly or the error page is served as a 200
            if (!headers_sent()) {
                http_response_code(500);
            }

            include(views_dir() . 'errors/500.php');
        }

        exit(1);
    }
}