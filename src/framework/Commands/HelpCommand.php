<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use TetherPHP\framework\Modules\Console;

class HelpCommand extends Command
{
    public string $command = 'help';

    public string $description = 'Displays help';

    /** @var array<string, string> */
    protected array $arguments = [
        'command' => 'Show the arguments and options for one command',
    ];

    public function execute(): int
    {
        // Registration diagnostics are written by the Console that dispatched
        // this command; building a second one here printed them all twice.
        $console = new Console('', errorStream: null);

        $name = $this->argument('command');

        if ($name !== '') {
            return $this->describe($console, $name);
        }

        $this->info("Available commands:");

        foreach ($console->commands as $command) {
            $instance = new $command();
            $this->info(" - {$instance->command} - \033[37m{$instance->description}\033[0m");
        }

        $this->line();
        $this->line("Run 'tether help <command>' for what a single command takes.");

        if ($console->skipped() !== []) {
            $this->error("\nSome commands could not be registered:");

            foreach ($console->skipped() as $reason) {
                $this->error("  {$reason}");
            }
        }

        return self::COMMAND_SUCCESS;
    }

    /**
     * What one command takes.
     *
     * Commands could declare arguments all along and nothing ever printed
     * them, so the only way to find out what `make:feature` wanted was to read
     * its source or run it and read the error.
     */
    private function describe(Console $console, string $name): int
    {
        if (!isset($console->commands[$name])) {
            $this->error("Command '{$name}' not found.");

            return self::COMMAND_ERROR;
        }

        $instance = new $console->commands[$name]();

        $this->info($instance->command . " - \033[37m{$instance->description}\033[0m");
        $this->line();
        $this->line('Usage: ' . $instance->usage());

        if ($instance->declaredArguments() !== []) {
            $this->line();
            $this->info('Arguments:');

            foreach ($instance->declaredArguments() as $argument => $description) {
                $this->line("  {$argument} - {$description}");
            }
        }

        if ($instance->declaredOptions() !== []) {
            $this->line();
            $this->info('Options:');

            foreach ($instance->declaredOptions() as $option => $description) {
                $this->line("  --{$option} - {$description}");
            }
        }

        return self::COMMAND_SUCCESS;
    }
}