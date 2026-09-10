<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use TetherPHP\framework\Traits\GeneratesTriples;
use TetherPHP\framework\Traits\Strings;

/**
 * Generates the seven ADR triples a CRUD resource is made of.
 *
 * The roadmap's own sequencing note is the argument for this command:
 * "Explicitness costs typing; pay for it with generators." A resource written
 * out by hand in this framework is seven Actions, seven Domains, seven Results,
 * seven Responders and four views, each one obvious and none of it interesting.
 *
 * It writes the same layout `make:feature` does — a directory per feature — and
 * shares its stubs. The only difference is that it writes seven operations into
 * that directory instead of one, which is why a feature can grow into a
 * resource by having files added to it rather than moved.
 *
 * What it does not do is add a `$router->resource()` that registers seven
 * routes from one line. That would put the route table somewhere a reader
 * cannot see it, which is exactly the auto-discovery Principle 3 forbids. The
 * seven routes are printed instead, for `routes/web.php`, where they stay
 * visible to a reader, to `tether routes`, and to `tether explain`.
 */
class MakeResourceCommand extends Command
{
    use GeneratesTriples;
    use Strings;

    public string $command = 'make:resource';

    public string $description = 'Create a full CRUD resource: seven Actions, Domains, Results, Responders and their views';

    /** @var array<string, string> */
    protected array $arguments = [
        'name' => 'The name of the resource, singular, e.g. Post',
    ];

    /** @var array<string, string> */
    protected array $options = [
        'uri' => 'The base URI to generate routes and redirects for (default: the name, kebab-cased)',
    ];

    public function execute(): int
    {
        $name = $this->argument('name');

        if ($name === '') {
            $this->error('Resource name cannot be empty.');

            return self::COMMAND_INVALID_ARGUMENT;
        }

        $feature = $this->toPascalCase($name);
        $viewName = $this->toKebabCase($feature);
        $uri = $this->baseUri($viewName);

        if ($uri === null) {
            return self::COMMAND_INVALID_ARGUMENT;
        }

        foreach ($this->resourceOperations($uri) as $operation => $spec) {
            $status = $this->writeTriple($feature, $operation, $viewName, $uri, $spec);

            if ($status !== self::COMMAND_SUCCESS) {
                return $status;
            }
        }

        $this->success("Resource '{$feature}' created.");
        $this->reportRoutes($feature, $uri);

        return self::COMMAND_SUCCESS;
    }

    /**
     * There is no pluraliser in the core and there is not going to be one.
     *
     * Guessing that Post becomes posts and Category becomes categories is a
     * table of English irregulars that would then be wrong about the one word
     * an application cares about. The default is the name as given; an
     * application that wants the conventional plural says so once.
     */
    private function baseUri(string $viewName): ?string
    {
        $uri = trim((string) $this->option('uri', '/' . $viewName));

        if ($uri === '') {
            $this->error('The --uri option cannot be empty.');

            return null;
        }

        if (!str_starts_with($uri, '/')) {
            $uri = '/' . $uri;
        }

        return rtrim($uri, '/') === '' ? '/' : rtrim($uri, '/');
    }

    /**
     * Nothing is written to routes/web.php.
     *
     * A generator that edits an application's route table would be the one
     * place a reader could no longer trust what they see, and re-running it
     * would have to work out whether it had already been. Printing the lines
     * keeps the route table something a person wrote.
     */
    private function reportRoutes(string $feature, string $uri): void
    {
        $this->line();
        $this->info('Add these to routes/web.php:');
        $this->line();

        foreach ($this->resourceOperations($uri) as $operation => $spec) {
            $this->line(sprintf(
                "    \$router->%s('%s', Actions\\%s\\%s::class);",
                $spec['verb'],
                $spec['route'],
                $feature,
                $operation,
            ));
        }

        $this->line();
        $this->line("Order does not matter: '{$uri}/create' is a static route and wins over '{$uri}/{id}'.");
        $this->line();
        $this->info('The update and delete forms need Middleware\OverridesMethod in routes/middleware.php:');
        $this->line();
        $this->line('    new TetherPHP\framework\Middleware\OverridesMethod(),');
        $this->line();
        $this->line("Without it a browser can only send GET and POST, so the PUT and DELETE routes 404.");
    }
}
