<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

class ServeCommand extends Command
{
    public string $command = 'serve';

    public string $description = 'Run the application on PHP\'s built-in server';

    /** @var array<string, string> */
    protected array $options = [
        'host' => 'Host to bind (default 127.0.0.1)',
        'port' => 'Port to bind (default 8000)',
    ];

    /**
     * A convenience with one trap worth stating out loud.
     *
     * The built-in server falls back to index.php for any path that is not a
     * real file, which is what `public/.htaccess` does on a real web server. So
     * a routing change that works here can still 404 everywhere else if the
     * .htaccess is missing — the server hides exactly the bug it is easiest to
     * ship. The notice below is the whole reason this command prints anything.
     */
    public function execute(): int
    {
        $root = project_root() . '/public';

        if (!is_dir($root)) {
            $this->error("No public directory at {$root}.");

            return self::COMMAND_ERROR;
        }

        $host = (string) $this->option('host', '127.0.0.1');
        $port = (string) $this->option('port', '8000');

        if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
            $this->error("'{$port}' is not a port.");

            return self::COMMAND_INVALID_ARGUMENT;
        }

        $this->info("TetherPHP on http://{$host}:{$port}");
        $this->line("Serving {$root}");
        $this->line();
        $this->line('This server falls back to index.php on its own. A real web server does not —');
        $this->line('public/.htaccess does it there, so test routing against Apache before shipping.');
        $this->line();

        $command = sprintf(
            '%s -S %s -t %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg("{$host}:{$port}"),
            escapeshellarg($root),
        );

        passthru($command, $status);

        return $status === 0 ? self::COMMAND_SUCCESS : self::COMMAND_ERROR;
    }
}
