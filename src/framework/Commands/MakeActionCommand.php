<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use TetherPHP\framework\Traits\GeneratesFiles;
use TetherPHP\framework\Traits\Strings;

class MakeActionCommand extends Command
{
    use GeneratesFiles;
    use Strings;

    public string $command = 'make:action';

    public string $description = 'Create an Action';

    /** @var array<string, string> */
    protected array $arguments = [
        'name' => 'The name of the action',
    ];

    public function execute(): int
    {
        $name = $this->argument('name');

        if ($name === '') {
            $this->error('Action name cannot be empty.');

            return self::COMMAND_INVALID_ARGUMENT;
        }

        $className = $this->toPascalCase($name);

        $status = $this->writeStub('Action', app_dir() . "/Actions/{$className}.php", [
            'className' => $className,
        ]);

        if ($status !== self::COMMAND_SUCCESS) {
            return $status;
        }

        // the generated Action constructs both in its constructor
        $this->warnIfMissing("Domains\\{$className}", app_dir() . "/Domains/{$className}.php", "make:domain {$name}");
        $this->warnIfMissing("Responders\\{$className}", app_dir() . "/Responders/{$className}.php", "make:responder {$name}");

        return self::COMMAND_SUCCESS;
    }
}
