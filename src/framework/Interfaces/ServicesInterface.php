<?php

declare(strict_types=1);

namespace TetherPHP\framework\Interfaces;

use TetherPHP\framework\Modules\Env;
use TetherPHP\framework\Modules\Log;

/**
 * What an application is made of, as one object.
 *
 * The application builds it in `public/index.php` and gives it to the Kernel;
 * the Kernel gives it to every Action. An Action hands its Domain the pieces
 * the Domain asks for by constructor — never the whole object, so a Domain's
 * signature still says exactly what it depends on and a unit test can build
 * one with a fake.
 *
 * Before this, an Action was constructed with only the Request, so anything a
 * Domain needed beyond that — a connection, a mailer, a clock — had no route
 * in but a global such as env(), which is a service locator with a small
 * vocabulary, and each application would have grown its own.
 *
 * The interface names the two things the Kernel itself runs on. It needs an
 * Env for APP_DEBUG and a Log for what goes wrong, and it installs both for
 * the env() and logger() helpers; asking for them here means they are built
 * once, in the same place as everything else, rather than handed to the Kernel
 * beside an object that also contains them. Everything past these two is the
 * application's own, and the framework never names an application class or
 * looks for anything in here by name.
 */
interface ServicesInterface
{
    public Env $env { get; }

    public Log $log { get; }
}
