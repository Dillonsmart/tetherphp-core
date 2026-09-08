<?php

declare(strict_types=1);

namespace TetherPHP\framework\Exceptions;

/**
 * The description is shown to the visitor, so it stays generic — the detail
 * that explains the failure belongs in the log, not in the response.
 */
class HttpInternalServerErrorException extends HttpException
{
    public function __construct(string $description = 'Something went wrong on our end.')
    {
        parent::__construct(500, 'Internal Server Error', $description);
    }
}
