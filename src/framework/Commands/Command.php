<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

class Command
{ // TODO when the command is executed we need to get the arguments and options from the command line input
    const int COMMAND_SUCCESS = 0;
    const int COMMAND_ERROR = 1;
    const int COMMAND_INVALID_ARGUMENT = 2;

    public string $command = '';

    public string $description = '';

    /** @var array<string, string> */
    protected array $arguments = [];

    /**
     * @param list<string> $args
     * @param array<string, string> $opts
     */
    public function __construct(public array $args = [], public array $opts = [])
    {}

    /**
     * Overridden by every real command. Declared here because Console calls it,
     * and a base class that does not declare what it calls is a lie.
     *
     * A native `int` return type would break any subclass that overrides this
     * without one, so it stays a docblock until the next breaking release.
     *
     * @return int one of the COMMAND_* constants
     */
    public function execute()
    {
        return self::COMMAND_ERROR;
    }

    public function info(string $message): void
    {
        echo "\033[34m{$message}\033[0m \n";
    }

    public function success(string $message): void
    {
        echo "\033[32m{$message}\033[0m \n";
    }

    public function error(string $message): void
    {
        echo "\033[31m{$message}\033[0m \n";
    }

    /**
     * @throws \InvalidArgumentException when the command does not declare $name
     */
    public function argument(string $name): string
    {
        if (array_key_exists($name, $this->arguments)) {
            $index = array_search($name, array_keys($this->arguments));
            return $this->args[$index] ?? '';
        }

        throw new \InvalidArgumentException("Argument '{$name}' not found in command '{$this->command}'.");
    }
}