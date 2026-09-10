<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use TetherPHP\framework\Traits\GeneratesTriples;
use TetherPHP\framework\Traits\Strings;

class MakeResponderCommand extends Command
{
    use GeneratesTriples;
    use Strings;

    public string $command = 'make:responder';

    public string $description = 'Create a Responder inside a feature, and the view it renders';

    /** @var array<string, string> */
    protected array $arguments = [
        'feature' => 'The feature the responder belongs to, e.g. Blog',
        'operation' => 'What the responder renders, e.g. Show (default: Index)',
    ];

    /**
     * The view comes with it: the generated Responder renders
     * `pages.<feature>.<operation>`, so shipping one without the other produces
     * code that throws "View not found" on first request.
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

        $status = $this->writeResponder($feature, $operation, $viewName, '/' . $viewName, $spec);

        if ($status !== self::COMMAND_SUCCESS) {
            return $status;
        }

        $shape = $this->resultShape($spec['result']);

        $this->warnIfMissing(
            "Domains\\{$feature}\\Results\\{$shape}",
            app_dir() . "/Domains/{$feature}/Results/{$shape}.php",
            "make:domain {$feature} {$operation}",
        );

        return self::COMMAND_SUCCESS;
    }
}
