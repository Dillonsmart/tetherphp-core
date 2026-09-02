<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

/*
 * The framework resolves an application through project_root(), which in this
 * repository is the repository itself. To exercise anything that reads app_dir()
 * or views_dir() — the Kernel, the Console, the make:* commands — a minimal
 * application has to exist at that root, so tests/Fixtures/app is linked into
 * place. Both the link and the .env below are gitignored.
 */
$root = dirname(__DIR__);

if (!file_exists($root . '/app')) {
    symlink($root . '/tests/Fixtures/app', $root . '/app');
}

if (!file_exists($root . '/.env')) {
    file_put_contents($root . '/.env', "APP_NAME=TetherPHP Tests\nAPP_DEBUG=false\n");
}

if (!is_dir($root . '/storage/logs')) {
    mkdir($root . '/storage/logs', 0755, true);
}
