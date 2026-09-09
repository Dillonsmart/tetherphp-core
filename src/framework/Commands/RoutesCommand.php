<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use TetherPHP\framework\Routing\Route;
use TetherPHP\framework\Traits\InspectsApplication;

class RoutesCommand extends Command
{
    use InspectsApplication;

    public string $command = 'routes';

    public string $description = 'Show the resolved route table';

    /** @var array<string, string> */
    protected array $options = [
        'method' => 'Only routes registered for this method',
    ];

    /**
     * Principle 6 taken literally: routing gained groups, dynamic segments,
     * view routes and three more verbs, and nothing could show the result. The
     * only way to know what a URL did was to read `routes/web.php` and simulate
     * the matcher in your head.
     *
     * A route whose Action is missing or not routable is marked here rather
     * than at 3am — the Kernel only finds out when someone requests it.
     */
    public function execute(): int
    {
        $router = $this->applicationRouter();

        if ($router === null) {
            return self::COMMAND_ERROR;
        }

        $routes = $this->routeList($router);

        $only = strtoupper((string) $this->option('method', ''));

        if ($only !== '') {
            $routes = array_values(array_filter($routes, static fn (array $r): bool => $r['method'] === $only));
        }

        if ($routes === []) {
            $this->info('No routes registered.');

            return self::COMMAND_SUCCESS;
        }

        $widths = [
            'method' => max(6, ...array_map(static fn (array $r): int => strlen($r['method']), $routes)),
            'uri' => max(3, ...array_map(static fn (array $r): int => strlen($r['uri']), $routes)),
            'type' => max(4, ...array_map(static fn (array $r): int => strlen($r['type']), $routes)),
        ];

        $this->info(sprintf(
            "%-{$widths['method']}s  %-{$widths['uri']}s  %-{$widths['type']}s  %s",
            'METHOD',
            'URI',
            'TYPE',
            'ACTION',
        ));

        foreach ($routes as $route) {
            $target = $route['type'] === Route::TYPE_VIEW
                ? "view: {$route['action']}"
                : $route['action'] . $this->annotate($route['action']);

            $this->line(sprintf(
                "%-{$widths['method']}s  %-{$widths['uri']}s  %-{$widths['type']}s  %s",
                $route['method'],
                $route['uri'],
                $route['type'],
                $target,
            ));
        }

        $this->line();
        $this->line(count($routes) . ' route(s). A static route wins over a dynamic route of the same shape.');

        return self::COMMAND_SUCCESS;
    }

    private function annotate(string $action): string
    {
        if (!class_exists($action)) {
            return "  \033[31m(class not found)\033[0m";
        }

        if (!$this->isRoutable($action)) {
            return "  \033[31m(does not implement ActionInterface)\033[0m";
        }

        return '';
    }
}
