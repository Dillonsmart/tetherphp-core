<?php

declare(strict_types=1);

namespace TetherPHP\framework\Traits;

/**
 * Writing a stub out to the application.
 *
 * `make:feature` had five near-identical private methods — create the
 * directory, refuse to overwrite, read the stub, substitute, write, report —
 * differing only in the path and the stub name. Splitting it into `make:action`,
 * `make:domain` and `make:responder` would have made that six, then eight. The
 * repeated part is here once; what each command knows is where its file goes.
 */
trait GeneratesFiles
{
    /**
     * Renders a stub and writes it, refusing to overwrite.
     *
     * @param array<string, string> $replacements placeholder => value, without the braces
     *
     * @return int one of the COMMAND_* constants
     */
    protected function writeStub(string $stub, string $path, array $replacements): int
    {
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            $this->error("Failed to create directory: {$directory}");

            return self::COMMAND_ERROR;
        }

        if (file_exists($path)) {
            $this->error("Already exists: {$path}");

            return self::COMMAND_ERROR;
        }

        $template = file_get_contents(core_dir() . "/Stubs/{$stub}.txt");

        if ($template === false) {
            $this->error("Could not read the {$stub} stub.");

            return self::COMMAND_ERROR;
        }

        $placeholders = array_map(static fn (string $key): string => '{{' . $key . '}}', array_keys($replacements));

        if (file_put_contents($path, str_replace($placeholders, array_values($replacements), $template)) === false) {
            $this->error("Failed to write: {$path}");

            return self::COMMAND_ERROR;
        }

        $this->success("Created: {$path}");

        return self::COMMAND_SUCCESS;
    }

    /**
     * Says so when a generated class references something that is not there yet.
     *
     * An Action names its Domain and Responder in its constructor, so
     * generating one on its own produces code that fatals on first request.
     * That is a legitimate thing to do — the other two may be coming — but it
     * must not be silent.
     */
    protected function warnIfMissing(string $class, string $path, string $command): void
    {
        if (!file_exists($path)) {
            $this->error("Note: {$class} does not exist yet. Create it with 'tether {$command}'.");
        }
    }
}
