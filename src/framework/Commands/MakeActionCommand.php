<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use TetherPHP\framework\Traits\GeneratesTriples;
use TetherPHP\framework\Traits\Strings;

class MakeActionCommand extends Command
{
    use GeneratesTriples;
    use Strings;

    public string $command = 'make:action';

    public string $description = 'Create an Action inside a feature';

    /** @var array<string, string> */
    protected array $arguments = [
        'feature' => 'The feature the action belongs to, e.g. Blog',
        'operation' => 'What the action does, e.g. Show (default: Index)',
    ];

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

        $status = $this->writeAction($feature, $operation, $viewName, '/' . $viewName, $spec);

        if ($status !== self::COMMAND_SUCCESS) {
            return $status;
        }

        // the generated Action constructs both in its constructor
        $this->warnIfMissing(
            "Domains\\{$feature}\\{$operation}",
            app_dir() . "/Domains/{$feature}/{$operation}.php",
            "make:domain {$feature} {$operation}",
        );

        $this->warnIfMissing(
            "Responders\\{$feature}\\{$operation}",
            app_dir() . "/Responders/{$feature}/{$operation}.php",
            "make:responder {$feature} {$operation}",
        );

        return self::COMMAND_SUCCESS;
    }
}
