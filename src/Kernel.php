<?php

namespace TetherPHP;

use Responders\Responder;
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

        // the lack of action indicates the route was not found
        if (!isset($route->action)) {
            include(views_dir() . 'errors/404.php');
            exit(404);
        }

        if ($route->type === 'view') {
            return new Responder($this->request)->view($route->action, []);
        } else {
            if (!class_exists($route->action)) {
                include(views_dir() . 'errors/500.php');
                exit(500);
            }
        }

        if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
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
            include(views_dir() . 'errors/500.php');
            exit(500);
        });

        set_exception_handler(function ($exception) {
            Log::error("Uncaught Exception: " . $exception->getMessage());
            Log::error("Uncaught Exception: " . $exception->getTraceAsString());
            include(views_dir() . 'errors/500.php');
            exit(500);
        });
    }
}