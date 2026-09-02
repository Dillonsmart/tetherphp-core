<?php

declare(strict_types=1);

namespace Commands;

// deliberately does not extend Command — Console must say so rather than skip silently
class NotACommand
{
    public string $command = 'fixture:bad';
}
