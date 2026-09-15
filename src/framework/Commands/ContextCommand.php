<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use Composer\InstalledVersions;
use TetherPHP\framework\Modules\Console;
use TetherPHP\framework\Routing\Route;
use TetherPHP\framework\Traits\InspectsApplication;
use TetherPHP\Router;

class ContextCommand extends Command
{
    use InspectsApplication;

    public string $command = 'context';

    public string $description = 'Emit a machine-readable map of the application as JSON';

    /** @var array<string, string> */
    protected array $options = [
        'pretty' => 'Indent the JSON for reading',
    ];

    /**
     * Principle 2, Agent Ready, with something to point at.
     *
     * An agent asked to add a feature has to know where files go, what the
     * naming convention is, and which route would conflict. Those facts are
     * spread across AGENTS.md, the directory layout and routes/web.php, and
     * every agent rediscovers them by reading the repository. This prints them
     * once, in a form that does not need reading comprehension.
     *
     * It writes to stdout with no colour codes and nothing else, so it can be
     * piped into a tool. Diagnostics go through error(), which the Console
     * writes to stderr.
     */
    public function execute(): int
    {
        $router = $this->applicationRouter();

        if ($router === null) {
            return self::COMMAND_ERROR;
        }

        $context = [
            'framework' => 'tetherphp',
            'version' => $this->version(),
            'root' => project_root(),
            'pipeline' => ['Request', 'Route', 'Action', 'Domain', 'Responder', 'Response'],
            'conventions' => $this->conventions(),
            'middleware' => $this->middleware(),
            'services' => $this->services($router),
            'routes' => $this->routes($router),
            'features' => $this->features($router),
            'commands' => $this->commands(),
        ];

        $flags = JSON_UNESCAPED_SLASHES | ($this->hasOption('pretty') ? JSON_PRETTY_PRINT : 0);

        $json = json_encode($context, $flags);

        if ($json === false) {
            $this->error('Could not encode the context: ' . json_last_error_msg());

            return self::COMMAND_ERROR;
        }

        echo $json . "\n";

        return self::COMMAND_SUCCESS;
    }

    /**
     * The version actually installed, asked of Composer.
     *
     * The Kernel used to define VERSION_NAME and VERSION as constants from two
     * hand-maintained properties. Nothing read them, and by v0.7.0 they still
     * said "0.5 alpha" — a version string that has to be remembered is a
     * version string that is wrong. Composer already knows.
     */
    private function version(): ?string
    {
        if (!InstalledVersions::isInstalled('dillonsmart/tetherphp-core')) {
            return null;
        }

        return InstalledVersions::getPrettyVersion('dillonsmart/tetherphp-core');
    }

    /**
     * @return array<string, mixed>
     */
    private function conventions(): array
    {
        return [
            'directories' => [
                'actions' => 'app/Actions',
                'domains' => 'app/Domains',
                'results' => 'app/Domains/<Feature>/Results',
                'responders' => 'app/Responders',
                'views' => 'app/Views',
                'commands' => 'app/Commands',
                'routes' => 'routes/web.php',
            ],
            'namespaces' => [
                'actions' => 'Actions',
                'domains' => 'Domains',
                'results' => 'Domains\\<Feature>\\Results',
                'responders' => 'Responders',
                'commands' => 'Commands',
            ],
            'naming' => 'Every feature is a directory, named in PascalCase, and each operation is a class '
                . 'inside it: Actions\\Blog\\Show, Domains\\Blog\\Show and Responders\\Blog\\Show are one '
                . 'operation of the Blog feature, and its view is app/Views/pages/blog/show.php. A Result is '
                . 'named for its shape and shared by the operations that answer the same way: '
                . 'Domains\\Blog\\Results\\Record.',
            'rules' => [
                'An Action implements ActionInterface and returns a Response.',
                'An Action is constructed with the Request and the application\'s Services, '
                . 'and hands its Domain the pieces the Domain asks for by constructor.',
                'A Domain returns a DomainResult and knows nothing about HTTP.',
                'A Responder names the view variables; a Result is named for its shape, not its operation.',
                'Routes match case-insensitively; a captured parameter arrives exactly as it was sent.',
                'A static route wins over a dynamic route of the same shape.',
                'A dynamic route only matches a URI with the same number of segments.',
                'End a request early by throwing an HttpException.',
            ],
            'generate' => [
                'feature' => 'tether make:feature <name>',
                'resource' => 'tether make:resource <name> [--uri=<base>]',
                'action' => 'tether make:action <feature> <operation>',
                'domain' => 'tether make:domain <feature> <operation>',
                'responder' => 'tether make:responder <feature> <operation>',
                'command' => 'tether make:command <name>',
            ],
        ];
    }

    /**
     * What runs around every request, in order.
     *
     * An agent reading this needs to know that a write will be refused without
     * a CSRF token, or that a guard stands in front of every route. Neither is
     * visible from the route table.
     *
     * @return array<string, mixed>
     */
    private function middleware(): array
    {
        ['names' => $names, 'problem' => $problem] = $this->applicationMiddleware();

        return [
            'wraps' => 'routing and the action, outermost first',
            'names' => $names,
            'problem' => $problem,
        ];
    }

    /**
     * What the application hands its Actions, and so what a Domain can be given.
     *
     * An agent adding a feature needs to know whether there is a database
     * connection to ask for and what it is called. That is declared in one
     * class, and this reports it without constructing it.
     *
     * @return array<string, mixed>
     */
    private function services(Router $router): array
    {
        $services = $this->applicationServices($router);

        if ($services === null) {
            return [
                'class' => null,
                'file' => null,
                'provides' => [],
                'note' => 'No routed Action takes a ServicesInterface. Domains can only be handed the Request.',
            ];
        }

        return [
            'class' => $services['class'],
            'file' => $services['file'],
            'provides' => $services['provides'],
            'note' => 'Built in public/index.php and handed to every Action; an Action passes a Domain the pieces it needs.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function routes(Router $router): array
    {
        return array_map(function (array $route): array {
            $routable = $route['type'] !== Route::TYPE_VIEW;

            return [
                'method' => $route['method'],
                'uri' => $route['uri'],
                'type' => $route['type'],
                'action' => $route['action'],
                'file' => $routable ? $this->classFile($route['action']) : null,
                'resolves' => $routable ? $this->isRoutable($route['action']) : null,
            ];
        }, $this->routeList($router));
    }

    /**
     * The ADR triples the routes reach, one entry per Action.
     *
     * @return list<array<string, mixed>>
     */
    private function features(Router $router): array
    {
        $features = [];

        foreach ($this->routeList($router) as $route) {
            if ($route['type'] === Route::TYPE_VIEW || isset($features[$route['action']])) {
                continue;
            }

            $features[$route['action']] = [
                'name' => $this->shortName($route['action']),
                'parts' => $this->triple($route['action']),
            ];
        }

        return array_values($features);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function commands(): array
    {
        $console = new Console('', errorStream: null);

        $commands = [];

        foreach ($console->commands as $class) {
            $instance = new $class();

            $commands[] = [
                'name' => $instance->command,
                'description' => $instance->description,
                'usage' => $instance->usage(),
                'arguments' => $instance->declaredArguments(),
                'options' => $instance->declaredOptions(),
            ];
        }

        return $commands;
    }
}
