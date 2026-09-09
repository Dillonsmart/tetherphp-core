<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use TetherPHP\framework\Traits\GeneratesFiles;
use TetherPHP\framework\Traits\Strings;

class MakeFeatureCommand extends Command
{
    use GeneratesFiles;
    use Strings;

    public string $command = 'make:feature';

    public string $description = 'Create a whole ADR triple: Action, Domain, Result, Responder and view';

    /** @var array<string, string> */
    protected array $arguments = [
        'name' => 'The name of the feature',
    ];

    /**
     * The five files of one feature, in the order they have to exist in.
     *
     * This used to be five private methods that each created a directory,
     * refused to overwrite, read a stub, substituted and reported — the same
     * six steps with a different path. They are now one list, and the same
     * writer backs `make:action`, `make:domain` and `make:responder`, so a
     * feature generated whole and a feature generated a piece at a time cannot
     * drift apart.
     *
     * Order matters: the Domain's return type names the Result, and the
     * Responder renders the view.
     */
    public function execute(): int
    {
        $name = $this->argument('name');

        if ($name === '') {
            $this->error('Feature name cannot be empty.');

            return self::COMMAND_INVALID_ARGUMENT;
        }

        $className = $this->toPascalCase($name);
        $viewName = $this->toKebabCase($className);

        $replacements = ['className' => $className, 'viewName' => $viewName];

        $files = [
            'Result' => app_dir() . "/Domains/Results/{$className}.php",
            'Domain' => app_dir() . "/Domains/{$className}.php",
            'Responder' => app_dir() . "/Responders/{$className}.php",
            'Action' => app_dir() . "/Actions/{$className}.php",
            'View' => app_dir() . "/Views/pages/{$viewName}/index.php",
        ];

        foreach ($files as $stub => $path) {
            $status = $this->writeStub($stub, $path, $replacements);

            if ($status !== self::COMMAND_SUCCESS) {
                return $status;
            }
        }

        $this->success("Feature '{$className}' created.");
        $this->info("Route it with: \$router->get('/{$viewName}', Actions\\{$className}::class);");

        return self::COMMAND_SUCCESS;
    }
}
