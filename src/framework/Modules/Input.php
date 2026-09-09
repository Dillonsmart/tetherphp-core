<?php

declare(strict_types=1);

namespace TetherPHP\framework\Modules;

/**
 * The tokens a command was invoked with, split into positional arguments and
 * named options.
 *
 * Nothing parsed options before this existed. `bin/tether` passed a literal
 * empty array for them and `Command::$opts` was public, documented and always
 * empty — a command could declare `--force` and had no way to read it. Anything
 * beginning with a dash simply became another positional argument, so
 * `make:feature Blog --force` generated a feature called "Blog" and silently
 * treated "--force" as a second argument nothing looked at.
 *
 * The rules are deliberately few, because a reader should be able to predict
 * how a command line splits without consulting this class:
 *
 *   --name=value   an option with a value
 *   --name         an option with an empty value; hasOption() is true
 *   -n=value, -n   the same, short form
 *   --             stops option parsing; everything after it is positional
 *   anything else  a positional argument, in the order it was given
 *
 * A negative number is therefore an option, not an argument. `--` is how you
 * pass one, and it is the reason `--` exists here at all.
 *
 * It lives beside Console rather than in Commands/ because every PHP file in
 * that directory is globbed and registered as a command — a class in there that
 * is not one gets reported as a broken command, correctly and unhelpfully.
 */
final class Input
{
    /**
     * @param list<string> $arguments
     * @param array<string, string> $options
     */
    public function __construct(
        private readonly array $arguments = [],
        private readonly array $options = [],
    ) {
    }

    /**
     * @param list<string> $tokens everything after the command name
     */
    public static function fromTokens(array $tokens): self
    {
        $arguments = [];
        $options = [];
        $literal = false;

        foreach ($tokens as $token) {
            if ($literal) {
                $arguments[] = $token;
                continue;
            }

            if ($token === '--') {
                $literal = true;
                continue;
            }

            $dashes = str_starts_with($token, '--') ? 2 : (str_starts_with($token, '-') ? 1 : 0);

            if ($dashes === 0 || strlen($token) === $dashes) {
                $arguments[] = $token;
                continue;
            }

            $body = substr($token, $dashes);

            [$name, $value] = str_contains($body, '=')
                ? explode('=', $body, 2)
                : [$body, ''];

            if ($name !== '') {
                $options[$name] = $value;
            }
        }

        return new self($arguments, $options);
    }

    public function argumentAt(int $index): ?string
    {
        return $this->arguments[$index] ?? null;
    }

    /** @return list<string> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    public function option(string $name, ?string $default = null): ?string
    {
        return $this->options[$name] ?? $default;
    }

    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }

    /** @return array<string, string> */
    public function options(): array
    {
        return $this->options;
    }
}
