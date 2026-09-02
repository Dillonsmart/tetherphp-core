<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use TetherPHP\framework\Traits\Strings;

class MakeCommand extends Command
{
    use Strings;

    public string $command = 'make:command';

    public string $description = 'Create a new command';

    public array $arguments = [
        'name' => 'The name of the command',
    ];

    public function execute(): int
    {
        $name = $this->argument('name');

        if (empty($name)) {
            $this->error("Command name cannot be empty.");
            return self::COMMAND_INVALID_ARGUMENT;
        }

        // 'send-emails', 'SendEmails' and 'SendEmailsCommand' all name the same command
        $baseName = preg_replace('/Command$/', '', $this->toValidClassName($name));

        if (empty($baseName)) {
            $this->error("'{$name}' is not a valid command name.");
            return self::COMMAND_INVALID_ARGUMENT;
        }

        $this->createCommandDirectory();

        $className = $baseName . 'Command';
        $commandName = $this->toKebabCase($baseName);

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