<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use TetherPHP\framework\Modules\Console;

class HelpCommand extends Command
{
    public string $command = 'help';

    public string $description = 'Displays help';

    public function execute(): int
    {
        // Registration diagnostics are written by the Console that dispatched
        // this command; building a second one here printed them all twice.
        $console = new Console('', errorStream: null);

        $this->info("Available commands:");

        foreach ($console->commands as $command) {
            $instance = new $command();
            $this->info(" - {$instance->command} - {$instance->description}");
        }

        if ($console->skipped() !== []) {
            $this->error("\nSome commands could not be registered:");

            foreach ($console->skipped() as $reason) {
                $this->error("  {$reason}");
            }
        }

        return self::COMMAND_SUCCESS;
    }
}