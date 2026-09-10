<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use TetherPHP\framework\Traits\GeneratesTriples;
use TetherPHP\framework\Traits\Strings;

class MakeDomainCommand extends Command
{
    use GeneratesTriples;
    use Strings;

    public string $command = 'make:domain';

    public string $description = 'Create a Domain inside a feature, and the Result it returns';

    /** @var array<string, string> */
    protected array $arguments = [
        'feature' => 'The feature the domain belongs to, e.g. Blog',
        'operation' => 'What the domain does, e.g. Show (default: Index)',
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
        $feature = $this->argument('feature');

        if ($feature === '') {
            $this->error('Feature name cannot be empty.');

            return self::COMMAND_INVALID_ARGUMENT;
        }

        $feature = $this->toPascalCase($feature);
        $operation = $this->toPascalCase($this->argument('operation') ?: 'Index');
        $viewName = $this->toKebabCase($feature);
        $page = $this->toKebabCase($operation);

        $spec = $this->pageOperation($feature, $operation, $viewName, $page);

        return $this->writeDomain($feature, $operation, $viewName, '/' . $viewName, $spec);
    }
}
