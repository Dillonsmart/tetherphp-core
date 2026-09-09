<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

class TestCommand extends Command
{
    public string $command = 'test';

    public string $description = 'Run the application\'s test suite';

    /**
     * Forwards to the application's own PHPUnit rather than shipping a runner.
     *
     * Principle 5: a test runner is not the framework's job, and vendoring one
     * into core would put PHPUnit in every production install. What the
     * framework owes is the entry point — `tether test` alongside `tether
     * serve` and `tether routes` — so there is one place to look for what you
     * can do to an application.
     *
     * Anything after the command is passed straight through, so
     * `tether test --filter=Blog` works.
     */
    public function execute(): int
    {
        $binary = project_root() . '/vendor/bin/phpunit';

        if (!is_file($binary)) {
            $this->error('No test runner found at vendor/bin/phpunit.');
            $this->line();
            $this->line('Install one with: composer require --dev phpunit/phpunit');

            return self::COMMAND_ERROR;
        }

        $arguments = array_map(escapeshellarg(...), $this->passthroughArguments());

        $command = escapeshellarg($binary) . ($arguments === [] ? '' : ' ' . implode(' ', $arguments));

        passthru($command, $status);

        return $status === 0 ? self::COMMAND_SUCCESS : self::COMMAND_ERROR;
    }

    /**
     * Everything the user typed after `test`, rebuilt for PHPUnit.
     *
     * @return list<string>
     */
    private function passthroughArguments(): array
    {
        $arguments = $this->input->arguments();

        foreach ($this->input->options() as $name => $value) {
            $arguments[] = $value === '' ? "--{$name}" : "--{$name}={$value}";
        }

        return $arguments;
    }
}
