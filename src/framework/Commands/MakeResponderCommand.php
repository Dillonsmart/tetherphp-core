<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use TetherPHP\framework\Traits\GeneratesFiles;
use TetherPHP\framework\Traits\Strings;

class MakeResponderCommand extends Command
{
    use GeneratesFiles;
    use Strings;

    public string $command = 'make:responder';

    public string $description = 'Create a Responder and the view it renders';

    /** @var array<string, string> */
    protected array $arguments = [
        'name' => 'The name of the responder',
    ];

    /**
     * The view comes with it: the generated Responder renders
     * `pages.<name>.index`, so shipping one without the other produces code
     * that throws "View not found" on first request.
     */
    public function execute(): int
    {
        $name = $this->argument('name');

        if ($name === '') {
            $this->error('Responder name cannot be empty.');

            return self::COMMAND_INVALID_ARGUMENT;
        }

        $className = $this->toPascalCase($name);
        $viewName = $this->toKebabCase($className);

        $status = $this->writeStub('Responder', app_dir() . "/Responders/{$className}.php", [
            'className' => $className,
            'viewName' => $viewName,
        ]);

        if ($status !== self::COMMAND_SUCCESS) {
            return $status;
        }

        $status = $this->writeStub('View', app_dir() . "/Views/pages/{$viewName}/index.php", [
            'className' => $className,
            'viewName' => $viewName,
        ]);

        if ($status !== self::COMMAND_SUCCESS) {
            return $status;
        }

        $this->warnIfMissing(
            "Domains\\Results\\{$className}",
            app_dir() . "/Domains/Results/{$className}.php",
            "make:domain {$name}",
        );

        return self::COMMAND_SUCCESS;
    }
}
