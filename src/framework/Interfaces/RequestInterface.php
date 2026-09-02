<?php

declare(strict_types=1);

namespace TetherPHP\framework\Interfaces;

interface RequestInterface
{
    public string $method {set; get;}
    public string $uri {set; get;}
}