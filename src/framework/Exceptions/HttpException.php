<?php

declare(strict_types=1);

namespace TetherPHP\framework\Exceptions;

/**
 * An error that is part of the HTTP conversation rather than a crash.
 *
 * The Kernel turns anything extending this into an error Response carrying its
 * status, title, description and headers, so an Action ends a request early
 * with `throw` and never builds the error page itself. Anything else that
 * escapes the pipeline is a bug: it is logged and becomes a 500.
 */
abstract class HttpException extends \Exception
{
    public function __construct(
        protected int $statusCode,
        protected string $title,
        protected string $description,
    ) {
        parent::__construct($description, $statusCode);
    }

    public function status(): int
    {
        return $this->statusCode;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * Headers the error response must carry — WWW-Authenticate on a 401,
     * Retry-After on a 429. Subclasses override; the base needs none.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [];
    }
}
