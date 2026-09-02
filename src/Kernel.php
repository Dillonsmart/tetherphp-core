<?php

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
     * @throws \Exception
     */
    public function run()
    {
        $this->request = new Request($this->session, $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], microtime(true));

        $route = $this->router->routeAction($this->request);

        if (!isset($route->action)) {
            http_response_code(404);
            include(views_dir() . 'errors/404.php');
            return '';
        }

        if ($route->type === 'view') {
            ob_start();
            include(views_dir() . str_replace('.', '/', $route->action) . '.php');
            return ob_get_clean();
        }

        if (!class_exists($route->action)) {
            http_response_code(500);
            include(views_dir() . 'errors/500.php');
            return '';
        }

        // CONTENT_TYPE is absent on any request without a body, which is most of them
        if (($_SERVER['CONTENT_TYPE'] ?? '') === 'application/json') {
            $this->request->payload = json_decode(file_get_contents('php://input'), true) ?? [];
        } else {
            $this->request->payload = $_POST ?? [];
        }

        $invokeAction = new $route->action($this->request);
        return $invokeAction();
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