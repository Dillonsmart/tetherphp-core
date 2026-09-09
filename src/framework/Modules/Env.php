<?php

declare(strict_types=1);

namespace TetherPHP\framework\Modules;

/**
 * The application's environment file, parsed once and held as a value.
 *
 * This used to be a singleton that located its own `.env` through
 * project_root() and constructed itself on first use. Nothing could say where a
 * value came from, and no test could supply a different set without moving a
 * file on disk — the anonymous subclass that `EnvParsingTest` needed to reach
 * loadEnv() was the evidence. An Env is now constructed with the variables it
 * holds, and reading the file is a separate, named step.
 *
 * `use()` and `current()` exist for exactly one caller: the `env()` helper.
 * Application code in a view or a Domain should not have to thread an object
 * through to read a setting, so the boot installs the instance that helper
 * delegates to. Framework classes take an Env through their constructor and
 * never call `current()`.
 */
final class Env
{
    private static ?self $current = null;

    /** @param array<string, string> $vars */
    public function __construct(private readonly array $vars = [])
    {
    }

    /**
     * @throws \RuntimeException when there is no file to read
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Environment file not found at {$path}.");
        }

        return new self(self::parse(file_get_contents($path) ?: ''));
    }

    /**
     * Installs the instance the `env()` helper reads.
     *
     * The Kernel calls this at boot, and `bin/tether` calls it when the
     * application has a `.env` to read.
     */
    public static function use(self $env): void
    {
        self::$current = $env;
    }

    /**
     * @throws \RuntimeException when nothing has been installed
     */
    public static function current(): self
    {
        if (self::$current === null) {
            throw new \RuntimeException(
                'No environment has been loaded. The Kernel installs one at boot; '
                . 'call Env::use(Env::fromFile($path)) first if you are running outside it.',
            );
        }

        return self::$current;
    }

    /**
     * A missing key returns the default rather than throwing.
     *
     * getEnv() used to throw and `env()` caught it, logged it and returned
     * null — an exception used as control flow between two halves of the same
     * feature. A key that is not set is not an error; it is a value the caller
     * has an opinion about.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        return $this->vars[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->vars);
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->vars;
    }

    /**
     * @return array<string, string>
     */
    private static function parse(string $contents): array
    {
        $vars = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // a line with no '=' is not a variable; skipping it beats destructuring
            // a one-element array and warning about the missing offset
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key = trim($key);
            $value = trim($value);

            // strip a trailing unquoted comment: APP_ENV=local # dev only
            if (!str_starts_with($value, '"') && !str_starts_with($value, "'")) {
                $value = trim(preg_replace('/\s+#.*$/', '', $value) ?? $value);
            }

            $vars[$key] = trim($value, "\"'");
        }

        return $vars;
    }
}
