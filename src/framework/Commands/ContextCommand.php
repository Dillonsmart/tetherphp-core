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
                'results' => 'app/Domains/Results',
                'responders' => 'app/Responders',
                'views' => 'app/Views',
                'commands' => 'app/Commands',
                'routes' => 'routes/web.php',
            ],
            'namespaces' => [
                'actions' => 'Actions',
                'domains' => 'Domains',
                'results' => 'Domains\\Results',
                'responders' => 'Responders',
                'commands' => 'Commands',
            ],
            'naming' => 'One name per feature in PascalCase. Actions\\Blog, Domains\\Blog, '
                . 'Domains\\Results\\Blog and Responders\\Blog are one feature; its view is '
                . 'app/Views/pages/blog/index.php.',
            'rules' => [
                'An Action implements ActionInterface and returns a Response.',
                'A Domain returns a DomainResult and knows nothing about HTTP.',
                'A Responder names the view variables; a Result is named for the domain.',
                'Route URIs are matched lowercased, so routing is case-insensitive.',
                'A static route wins over a dynamic route of the same shape.',
                'A dynamic route only matches a URI with the same number of segments.',
                'End a request early by throwing an HttpException.',
            ],
            'generate' => [
                'feature' => 'tether make:feature <name>',
                'action' => 'tether make:action <name>',
                'domain' => 'tether make:domain <name>',
                'responder' => 'tether make:responder <name>',
                'command' => 'tether make:command <name>',
            ],
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
