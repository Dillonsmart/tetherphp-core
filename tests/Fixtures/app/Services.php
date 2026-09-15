<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Fixtures\app;

use TetherPHP\framework\Interfaces\ServicesInterface;
use TetherPHP\framework\Modules\Env;
use TetherPHP\framework\Modules\Log;

/**
 * What the fixture application hands its Actions.
 *
 * The Env and the Log are what the interface asks for. The signature is one
 * property of the fixture's own, enough to prove the object the Kernel was
 * given is the one an Action receives, and to give `inspect` and `context`
 * something application-specific to list.
 */
final readonly class Services implements ServicesInterface
{
    public function __construct(
        public Env $env,
        public Log $log,
        public string $signature = '',
    ) {
    }
}
