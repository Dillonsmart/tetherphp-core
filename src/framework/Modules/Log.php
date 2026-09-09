<?php

declare(strict_types=1);

namespace TetherPHP\framework\Modules;

/**
 * Appends lines to a log file in a configured directory.
 *
 * Every method used to be static and the directory came from storage_dir(), so
 * a Log could only ever write to one place and nothing that used it could be
 * tested without writing into the repository. The destination is now a
 * constructor argument, which is what makes a Kernel under test able to log
 * into a temporary directory.
 *
 * `use()` and `current()` back the `logger()` helper, on the same terms as
 * Env: the boot installs the instance, framework classes are given one.
 */
final class Log
{
    private static ?self $current = null;

    public function __construct(private readonly string $directory)
    {
    }

    /**
     * Installs the instance the `logger()` helper writes to.
     */
    public static function use(self $log): void
    {
        self::$current = $log;
    }

    /**
     * @throws \RuntimeException when nothing has been installed
     */
    public static function current(): self
    {
        if (self::$current === null) {
            throw new \RuntimeException(
                'No log has been configured. The Kernel installs one at boot; '
                . 'call Log::use(new Log($directory)) first if you are running outside it.',
            );
        }

        return self::$current;
    }

    public function error(string $message): void
    {
        $this->write('error', $message);
    }

    public function info(string $message): void
    {
        $this->write('info', $message);
    }

    /**
     * Appends one line to today's log file.
     *
     * mkdir() and file_put_contents() report failure by returning false and
     * raising a warning — they do not throw — so a try/catch here would never
     * fire and an unwritable storage directory would lose every log silently.
     * Failures fall back to PHP's own error log instead.
     */
    private function write(string $level, string $message): void
    {
        $directory = rtrim($this->directory, '/') . '/';

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            error_log("TetherPHP: cannot create log directory {$directory}");
            error_log("[{$level}] {$message}");

            return;
        }

        $file = $directory . date('Y-m-d') . '.log';
        $line = '[' . date('Y-m-d H:i:s') . "] [{$level}] {$message}" . PHP_EOL;

        // LOCK_EX: concurrent requests append to the same file and would
        // otherwise interleave mid-line
        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log("TetherPHP: cannot write to {$file}");
            error_log("[{$level}] {$message}");
        }
    }
}
