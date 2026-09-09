<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use TetherPHP\framework\Routing\Route;
use TetherPHP\framework\Traits\InspectsApplication;

class ExplainCommand extends Command
{
    use InspectsApplication;

    public string $command = 'explain';

    public string $description = 'Show the path a URI takes through the pipeline';

    /** @var array<string, string> */
    protected array $arguments = [
        'uri' => 'The URI to resolve, e.g. /blog/hello',
    ];

    /** @var array<string, string> */
    protected array $options = [
        'method' => 'The HTTP method to resolve as (default GET)',
    ];

    /**
     * The pipeline principle made executable.
     *
     * `Request → Route → Action → Domain → Responder → Response` is the claim
     * the framework makes about itself, and until now the only way to check it
     * for a given URL was to read four files and hold the matcher in your head.
     *
     * Every line of the output is something checked against the application on
     * disk. Where a link is a naming convention rather than something the
     * framework resolved — an Action's Domain and Responder are constructed by
     * the Action itself and could be called anything — it says so.
     */
    public function execute(): int
    {
        $uri = $this->argument('uri');

        if ($uri === '') {
            $this->error('A URI is required, e.g. tether explain /blog');

            return self::COMMAND_INVALID_ARGUMENT;
        }

        if (!str_starts_with($uri, '/')) {
            $uri = '/' . $uri;
        }

        $router = $this->applicationRouter();

        if ($router === null) {
            return self::COMMAND_ERROR;
        }

        $method = strtoupper((string) $this->option('method', 'GET'));

        // Request lowercases the URI through a property hook, so routing is
        // case-insensitive and captured parameters arrive lowercased. Resolving
        // any other way here would explain a request that cannot happen.
        $path = strtolower(parse_url($uri, PHP_URL_PATH) ?: $uri);

        $this->info($this->label('Request') . "{$method} {$path}");

        if ($path !== strtolower($uri)) {
            $this->line($this->label('') . 'query string dropped before matching');
        }

        $route = $router->match($method, $path);

        if (!$route->matched) {
            $this->line();
            $this->error($this->label('Route') . 'no match — the Kernel throws HttpNotFoundException, which becomes a 404 Response.');
            $this->line();
            $this->line("Run 'tether routes' to see what is registered.");

            return self::COMMAND_SUCCESS;
        }

        $this->line($this->label('Route') . $route->type);

        if ($route->params !== []) {
            foreach ($route->params as $name => $value) {
                $this->line($this->label('') . "\$this->request->params['{$name}'] = '{$value}'");
            }
        }

        if ($route->isView()) {
            return $this->explainView($route->action);
        }

        return $this->explainAction($route->action);
    }

    /**
     * One column for the stage, one for what it resolved to. 'Responder' is the
     * longest stage name, so it sets the width.
     */
    private function label(string $stage): string
    {
        return str_pad($stage, 9) . ' ';
    }

    private function explainView(string $view): int
    {
        $file = 'app/Views/' . str_replace('.', '/', $view) . '.php';

        $this->line($this->label('View') . $view);
        $this->line($this->label('') . $file . (is_file(project_root() . '/' . $file) ? '' : "  \033[31m(missing — 500)\033[0m"));
        $this->line();
        $this->line($this->label('Response') . 'the Kernel renders the view itself; no Action, Domain or Responder is involved.');

        return self::COMMAND_SUCCESS;
    }

    private function explainAction(string $action): int
    {
        if (!class_exists($action)) {
            $this->line();
            $this->error($this->label('Action') . "{$action} does not exist — the Kernel logs it and returns a 500.");

            return self::COMMAND_SUCCESS;
        }

        if (!$this->isRoutable($action)) {
            $this->line();
            $this->error($this->label('Action') . "{$action} does not implement ActionInterface — the Kernel returns a 500.");

            return self::COMMAND_SUCCESS;
        }

        $triple = $this->triple($action);

        $this->line($this->label('Action') . $action);
        $this->line($this->label('') . (string) $triple['action']['file']);

        foreach (['domain' => 'Domain', 'result' => 'Result', 'responder' => 'Responder'] as $role => $label) {
            $part = $triple[$role];
            $line = $this->label($label) . $part['class'];

            $this->line($part['exists']
                ? $line . "\n" . $this->label('') . (string) $part['file']
                : $line . "  \033[31m(not found)\033[0m");
        }

        $this->line();
        $this->line($this->label('Response') . 'whatever the Responder returns; public/index.php calls send() on it.');
        $this->line();
        $this->line('Domain, Result and Responder are matched to the Action by name. An Action builds');
        $this->line('its own in its constructor and may use others — read the Action to be certain.');

        return self::COMMAND_SUCCESS;
    }
}
