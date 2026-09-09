<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use TetherPHP\framework\Modules\Input;

class Command
{
    const int COMMAND_SUCCESS = 0;
    const int COMMAND_ERROR = 1;
    const int COMMAND_INVALID_ARGUMENT = 2;

    public string $command = '';

    public string $description = '';

    /**
     * The positional arguments this command takes, in the order they are given
     * on the command line, as name => description.
     *
     * The order here **is** the contract: the first key is the first argument.
     * That was true before too, but only as a side effect of array_search()
     * over the keys, which returns false for a name that is not declared and
     * was used as an array index without checking.
     *
     * @var array<string, string>
     */
    protected array $arguments = [];

    /**
     * The options this command understands, as name => description. Declaring
     * one does not make it required; it makes it appear in `tether help`.
     *
     * @var array<string, string>
     */
    protected array $options = [];

    public function __construct(protected Input $input = new Input())
    {
    }

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

    public function line(string $message = ''): void
    {
        echo "{$message}\n";
    }

    /**
     * The value given for a declared argument, or '' if it was not supplied.
     *
     * @throws \InvalidArgumentException when the command does not declare $name —
     *         a bug in the command, not in what the user typed
     */
    public function argument(string $name): string
    {
        $index = array_search($name, array_keys($this->arguments), true);

        if ($index === false) {
            throw new \InvalidArgumentException(
                "Argument '{$name}' is not declared by command '{$this->command}'.",
            );
        }

        return $this->input->argumentAt($index) ?? '';
    }

    public function option(string $name, ?string $default = null): ?string
    {
        return $this->input->option($name, $default);
    }

    public function hasOption(string $name): bool
    {
        return $this->input->hasOption($name);
    }

    /**
     * How the command is invoked, for `tether help`.
     */
    public function usage(): string
    {
        $usage = "tether {$this->command}";

        foreach (array_keys($this->arguments) as $argument) {
            $usage .= " <{$argument}>";
        }

        foreach (array_keys($this->options) as $option) {
            $usage .= " [--{$option}]";
        }

        return $usage;
    }

    /** @return array<string, string> */
    public function declaredArguments(): array
    {
        return $this->arguments;
    }

    /** @return array<string, string> */
    public function declaredOptions(): array
    {
        return $this->options;
    }
}
