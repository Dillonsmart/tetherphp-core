<?php

declare(strict_types=1);

namespace TetherPHP\framework\Exceptions;

class HttpNotFoundException extends HttpException
{
    public function __construct(string $description = 'The requested resource could not be found.')
    {
        parent::__construct(404, 'Not Found', $description);
    }
}
