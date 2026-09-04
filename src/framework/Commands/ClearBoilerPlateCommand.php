<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

class ClearBoilerPlateCommand extends Command
{
    public string $command = 'boilerplate:clear';

    public string $description = 'Clears all the boilerplate files from the project';

    /** @var array<string, string> */
    protected array $arguments = [];

    /**
     * The base classes every generated Action, Domain and Responder extends.
     *
     * These were previously listed as 'Action.txt', which can never match a .php
     * file, so the command deleted app/Actions/Action.php — the base class — and
     * broke every Action in the project.
     *
     * @var list<string>
     */
    protected array $protectedFiles = [
        'Action.php',
        'Domain.php',
        'Responder.php',
    ];

    public function execute(): int
    {
        $files = $this->boilerplateFiles();

        if ($files === []) {
            $this->info("Nothing to clear.");
            return self::COMMAND_SUCCESS;
        }

        $this->info("This will permanently delete " . count($files) . " file(s):");

        foreach ($files as $file) {
            $this->info('  ' . $file);
        }

        $this->info("...and empty routes/web.php.");

        if (!$this->confirm()) {
            $this->info("Aborted.");
            return self::COMMAND_SUCCESS;
        }

        $failed = 0;

        foreach ($files as $file) {
            if (unlink($file)) {
                $this->success("Deleted: {$file}");
            } else {
                $this->error("Could not delete: {$file}");
                $failed++;
            }
        }

        if (!$this->clearRoutes()) {
            $failed++;
        }

        if ($failed > 0) {
            $this->error("Finished with {$failed} failure(s).");
            return self::COMMAND_ERROR;
        }

        $this->success("Boilerplate files cleared successfully.");
        return self::COMMAND_SUCCESS;
    }

    /**
     * Every .php file under app/, less the base classes.
     *
     * glob('app/**' . '/*.php') only ever matched one directory deep — PHP's glob
     * has no globstar — so views nested under app/Views/pages were left behind.
     *
     * @return list<string>
     */
    private function boilerplateFiles(): array
    {
        $appDir = app_dir();

        if (!is_dir($appDir)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appDir, \FilesystemIterator::SKIP_DOTS)
        );

        $files = [];

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (in_array($file->getFilename(), $this->protectedFiles, true)) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    /**
     * Destructive and irreversible, so it asks first. `--force` skips the prompt
     * for scripted use.
     */
    private function confirm(): bool
    {
        if (in_array('--force', $this->args, true)) {
            return true;
        }

        if (!defined('STDIN')) {
            $this->error("Refusing to delete without a terminal to confirm on. Pass --force.");
            return false;
        }

        $this->info("Type 'yes' to continue: ");

        return trim((string) fgets(STDIN)) === 'yes';
    }

    private function clearRoutes(): bool
    {
        $routesFile = project_root() . '/routes/web.php';

        if (!file_exists($routesFile)) {
            $this->info("Routes file does not exist: {$routesFile}");
            return true;
        }

        $stub = <<<'PHP'
        <?php

        use TetherPHP\Router;

        return function (Router $router) {
            //
        };

        PHP;

        if (file_put_contents($routesFile, $stub) === false) {
            $this->error("Could not clear routes file: {$routesFile}");
            return false;
        }

        $this->success("Cleared routes file: {$routesFile}");
        return true;
    }
}
