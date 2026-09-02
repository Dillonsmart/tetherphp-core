<?php

declare(strict_types=1);

namespace TetherPHP\framework\Interfaces;

interface ActionInterface
{
    public function __invoke(): string;
}