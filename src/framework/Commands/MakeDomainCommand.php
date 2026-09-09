<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use TetherPHP\framework\Traits\GeneratesFiles;
use TetherPHP\framework\Traits\Strings;

class MakeDomainCommand extends Command
{
    use GeneratesFiles;
    use Strings;

    public string $command = 'make:domain';

    public string $description = 'Create a Domain and the Result it returns';

    /** @var array<string, string> */
    protected array $arguments = [
        'name' => 'The name of the domain',
    ];

    /**
     * The Result comes with it, always.
     *
     * `Domain::handle()` is typed to return its Result, so a Domain generated
     * without one does not load. They are one unit of work even though they are
     * two files, and there is no `make:result` for the same reason.
     */
    public function execute(): int
    {
        $name = $this->argument('name');

        if ($name === '') {
            $this->error('Domain name cannot be empty.');

            return self::COMMAND_INVALID_ARGUMENT;
        }

        $className = $this->toPascalCase($name);

        $status = $this->writeStub('Result', app_dir() . "/Domains/Results/{$className}.php", [
            'className' => $className,
        ]);

        if ($status !== self::COMMAND_SUCCESS) {
            return $status;
        }

        return $this->writeStub('Domain', app_dir() . "/Domains/{$className}.php", [
            'className' => $className,
        ]);
    }
}
