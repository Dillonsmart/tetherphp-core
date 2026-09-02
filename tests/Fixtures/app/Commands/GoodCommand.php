<?php

declare(strict_types=1);

namespace Commands;

use TetherPHP\framework\Commands\Command;

class GoodCommand extends Command
{
    public string $command = 'fixture:good';

    public string $description = 'A command that registers';

    public function execute(): int
    {
        return self::COMMAND_SUCCESS;
    }
}
