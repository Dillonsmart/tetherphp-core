<?php

declare(strict_types=1);

use TetherPHP\framework\Modules\Env;
use TetherPHP\framework\Modules\Log;
use TetherPHP\framework\Sessions\Session;
use TetherPHP\Tests\Fixtures\app\Middleware\AddsHeader;

/*
 * The middleware declaration the introspection commands are tested against.
 *
 * A VerifyCsrfToken is built here on purpose: constructing it must not start a
 * session, which is the whole reason the declaration could be moved out of
 * public/index.php and made visible to the tooling.
 */
return function (Env $env, Log $log): array {
    return [
        new AddsHeader('X-Fixture', 'yes'),
        new TetherPHP\framework\Middleware\VerifyCsrfToken(new Session(), $log),
    ];
};
