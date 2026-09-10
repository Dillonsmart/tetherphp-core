<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Fixtures;

use TetherPHP\Kernel;

/**
 * A Kernel whose request body a test can supply.
 *
 * `php://input` is empty under the CLI, so without this the only body a test
 * could send was a POST through `$_POST` — which is exactly the case that
 * always worked, and none of the three that did not. Every other input the
 * Kernel reads is a superglobal a test can simply assign.
 */
final class KernelWithBody extends Kernel
{
    public string $requestBody = '';

    protected function body(): string
    {
        return $this->requestBody;
    }
}
