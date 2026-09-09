<?php

declare(strict_types=1);

use Composer\InstalledVersions;

function project_root(): string
{
    $rootPackage = InstalledVersions::getRootPackage();

    // Composer reports this relative to vendor/composer, so resolve it rather
    // than handing '<root>/vendor/composer/../..' to every other path helper
    return realpath($rootPackage['install_path']) ?: rtrim($rootPackage['install_path'], '/');
}

function package_root(): string
{
    // this file lives at <package>/src/framework/Helpers/
    return dirname(__DIR__, 3);
}

function app_dir(): string
{
    return project_root() . '/app';
}

function storage_dir(): string
{
    return project_root() . '/storage/';
}

function views_dir(): string
{
    return app_dir() . '/Views/';
}

function public_dir(): string
{
    return project_root() . '/public/';
}

function core_dir(): string
{
    // resolved relative to this file rather than the package root, so it holds
    // wherever Composer installs the package
    return dirname(__DIR__);
}

function core_views(): string
{
    return core_dir() . '/Views/';
}

/**
 * The one way application code reads a setting.
 *
 * A thin delegate to the Env the Kernel installed at boot — it holds no state
 * of its own and makes no decision the object does not. A missing key returns
 * $default; Env::current() throws only if nothing was booted at all, which is a
 * bug in the boot rather than a missing variable.
 */
function env(string $key, ?string $default = null): ?string
{
    return \TetherPHP\framework\Modules\Env::current()->get($key, $default);
}

/**
 * The one way application code writes a log line.
 */
function logger(string $message, string $level = 'info'): void
{
    $log = \TetherPHP\framework\Modules\Log::current();

    if ($level === 'error') {
        $log->error($message);

        return;
    }

    $log->info($message);
}
