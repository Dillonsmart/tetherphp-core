<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use TetherPHP\framework\Traits\Strings;

class MakeCommand extends Command
{
    use Strings;

    public string $command = 'make:command';

    public string $description = 'Create a new command';

    /** @var array<string, string> */
    protected array $arguments = [
        'name' => 'The name of the command',
    ];

    public function execute(): int
    {
        $name = $this->argument('name');

        if (empty($name)) {
            $this->error("Command name cannot be empty.");
            return self::COMMAND_INVALID_ARGUMENT;
        }

        if (preg_match('/[^A-Za-z0-9:_-]/', $name) === 1) {
            $this->error("'{$name}' is not a valid command name: letters, digits, '-', '_' and ':' only.");
            return self::COMMAND_INVALID_ARGUMENT;
        }

        // 'send-emails', 'SendEmails' and 'SendEmailsCommand' all name the same
        // command. A colon namespaces it the way the framework's own commands are
        // — 'db:schema' is class DbSchemaCommand and is invoked as db:schema — so
        // each segment is cased on its own and the colon survives into the name
        // but never into the class, where it would be a parse error.
        $segments = array_values(array_filter(explode(':', $name), static fn (string $s): bool => $s !== ''));

        if ($segments === []) {
            $this->error("'{$name}' is not a valid command name.");
            return self::COMMAND_INVALID_ARGUMENT;
        }

        $last = array_key_last($segments);
        $segments[$last] = preg_replace('/Command$/', '', $this->toPascalCase($segments[$last])) ?? '';
        $segments = array_map($this->toPascalCase(...), $segments);

        if (implode('', $segments) === '') {
            $this->error("'{$name}' is not a valid command name.");
            return self::COMMAND_INVALID_ARGUMENT;
        }

        $this->createCommandDirectory();

        $className = implode('', $segments) . 'Command';
        $commandName = implode(':', array_map($this->toKebabCase(...), $segments));

        $commandFilePath = app_dir() . "/Commands/{$className}.php";

        if (file_exists($commandFilePath)) {
            $this->error("Command already exists: {$commandFilePath}");
            return self::COMMAND_ERROR;
        }

        $template = file_get_contents(core_dir() . '/Stubs/Command.txt') ?: '';
        $template = str_replace(
            ['{{className}}', '{{commandName}}'],
            [$className, $commandName],
            $template
        );

        if (file_put_contents($commandFilePath, $template) === false) {
            $this->error("Failed to create command file: {$commandFilePath}");
            return self::COMMAND_ERROR;
        }

        $this->success("Command created successfully: {$commandFilePath}");
        $this->info("Run it with: php tether {$commandName}");
        return self::COMMAND_SUCCESS;
    }

    public function createCommandDirectory(): int
    {
        $commandDir = app_dir() . '/Commands';

        if (!is_dir($commandDir)) {
            if (!mkdir($commandDir, 0755, true)) {
                $this->error("Failed to create directory: {$commandDir}");
                return self::COMMAND_ERROR;
            }
        }

        $this->success("Command directory created at: {$commandDir}");
        return self::COMMAND_SUCCESS;
    }
}