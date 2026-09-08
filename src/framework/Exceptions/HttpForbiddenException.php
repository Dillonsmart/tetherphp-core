<?php

declare(strict_types=1);

namespace TetherPHP\framework\Exceptions;

class HttpForbiddenException extends HttpException
{
    public function __construct(string $description = 'You are not allowed to access this resource.')
    {
        parent::__construct(403, 'Forbidden', $description);
    }
}
