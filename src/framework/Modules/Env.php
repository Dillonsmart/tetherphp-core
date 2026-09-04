<?php

declare(strict_types=1);

namespace TetherPHP\framework\Modules;

class Env
{
    protected static ?Env $instance = null;
    protected string $basePath = '';
    /** @var array<string, string> */
    protected array $envVars = [];

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->basePath = project_root();
        $this->loadEnv();
    }

    public static function getInstance(): Env
    {
        if (self::$instance === null) {
            self::$instance = new Env();
        }
        return self::$instance;
    }

    /**
     * @throws \Exception
     */
    public function loadEnv(): void
    {
        $envFile = $this->basePath . '/.env';

        if(!file_exists($envFile)){
            throw new \Exception('Env file not found');
        }

        $envContent = file_get_contents($envFile) ?: '';

        foreach (preg_split('/\R/', $envContent) ?: [] as $line) {
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

            $this->envVars[$key] = trim($value, "\"'");
        }
    }

    /**
     * @throws \Exception
     */
    public function getEnv(string $key): string
    {
        if (array_key_exists($key, $this->envVars)) {
            return $this->envVars[$key];
        }

        // not logged here — the caller decides whether a missing key is a problem,
        // and env() already logs the ones it swallows
        throw new \Exception("Environment variable '{$key}' not found.");
    }
}