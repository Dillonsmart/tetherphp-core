<?php

declare(strict_types=1);

namespace TetherPHP\framework\Modules;

use TetherPHP\framework\Commands\Command;

class Console
{
    /** @var array<string, class-string<Command>> */
    public array $commands = [];

    /** @var string[] reasons commands were not registered */
    private array $skipped = [];

    /** @var resource|null where registration diagnostics are written */
    private $errorStream;

    /**
     * @param resource|null $errorStream defaults to stderr; pass a stream to capture
     */
    public function __construct(public string $command, $errorStream = null)
    {
        $this->errorStream = $errorStream ?? (defined('STDERR') ? STDERR : null);

        $this->registerCommands();
        $this->reportSkipped();
    }

    /**
     * Written to stderr so it is visible on every invocation without polluting a
     * command's own output, and so CI can see it.
     */
    private function reportSkipped(): void
    {
        if ($this->skipped === [] || $this->errorStream === null) {
            return;
        }

        foreach ($this->skipped as $reason) {
            fwrite($this->errorStream, "\033[33mSkipped command: {$reason}\033[0m\n");
        }
    }

    public function registerCommands(): void
    {
        $tetherCommands =  [
            ...(glob(__DIR__ . "/../Commands/*.php") ?: []),
        ];

        $customCommands = [
            ...(glob(app_dir() . "/Commands/*.php") ?: []),
        ];

        foreach ($tetherCommands as $commandClass) {
            $className = 'TetherPHP\\framework\\Commands\\' . basename($commandClass, '.php');

            // Command is the base class the others extend, not a command itself
            if ($className === Command::class) {
                continue;
            }

            $this->addCommand($className);
        }

        foreach ($customCommands as $commandClass) {
            $className = 'Commands\\' . basename($commandClass, '.php');

            $this->addCommand($className);
        }
    }

    /**
     * A command that cannot be registered says why. Failing closed here used to
     * be silent, which made a misnamed class, a missing psr-4 mapping and a file
     * that was never written indistinguishable from one another.
     */
    private function addCommand(string $className): void
    {
        if (!class_exists($className)) {
            $this->skipped[] = "{$className} could not be autoloaded — check the class name matches the filename, and that its namespace has a psr-4 mapping in composer.json";
            return;
        }

        if (!is_subclass_of($className, Command::class)) {
            $this->skipped[] = "{$className} does not extend " . Command::class;
            return;
        }

        $commandInstance = new $className();

        if ($commandInstance->command === '') {
            $this->skipped[] = "{$className} does not set a \$command name";
            return;
        }

        if (isset($this->commands[$commandInstance->command])) {
            $this->skipped[] = "{$className} claims '{$commandInstance->command}', already registered by {$this->commands[$commandInstance->command]}";
            return;
        }

        $this->commands[$commandInstance->command] = $className;
    }

    /**
     * Reasons commands were not registered, for `help` to report.
     */
    /** @return string[] */
    public function skipped(): array
    {
        return $this->skipped;
    }

    /**
     * @param list<string> $args
     * @param array<string, string> $options
     */
    public function executeCommand(array $args = [], array $options = []): int
    {
        if (isset($this->commands[$this->command])) {
            $commandInstance = new $this->commands[$this->command]($args, $options);
            return $commandInstance->execute();
        }

        // TODO - If command methods are going to be called directly, maybe consider a shared trait.
        (new \TetherPHP\framework\Commands\Command)->error("Command {$this->command} not found.");
        return Command::COMMAND_ERROR;
    }
}