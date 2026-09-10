<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use TetherPHP\framework\Traits\GeneratesTriples;
use TetherPHP\framework\Traits\Strings;

/**
 * One feature: one directory, one operation in it.
 *
 * This used to write five flat files — `app/Actions/Blog.php`,
 * `app/Domains/Blog.php` and so on — while `make:resource` wrote a directory
 * per resource. Two layouts for one concept, and the flat one was a dead end:
 * the moment a feature needed a second route, four files had to move and four
 * namespaces had to be rewritten.
 *
 * A feature is now a resource with one operation. Add a second route to it with
 * `make:action Blog Show` and nothing moves.
 */
class MakeFeatureCommand extends Command
{
    use GeneratesTriples;
    use Strings;

    public string $command = 'make:feature';

    public string $description = 'Create a whole ADR triple: Action, Domain, Result, Responder and view';

    /** @var array<string, string> */
    protected array $arguments = [
        'name' => 'The name of the feature',
    ];

    public function execute(): int
    {
        $name = $this->argument('name');

        if ($name === '') {
            $this->error('Feature name cannot be empty.');

            return self::COMMAND_INVALID_ARGUMENT;
        }

        $feature = $this->toPascalCase($name);
        $viewName = $this->toKebabCase($feature);
        $uri = '/' . $viewName;

        $spec = $this->pageOperation($feature, 'Index', $viewName, 'index');

        $status = $this->writeTriple($feature, 'Index', $viewName, $uri, $spec);

        if ($status !== self::COMMAND_SUCCESS) {
            return $status;
        }

        $this->success("Feature '{$feature}' created.");
        $this->info("Route it with: \$router->get('{$uri}', Actions\\{$feature}\\Index::class);");
        $this->line();
        $this->line("Add another page to it with 'tether make:action {$feature} <Operation>'.");

        return self::COMMAND_SUCCESS;
    }
}
