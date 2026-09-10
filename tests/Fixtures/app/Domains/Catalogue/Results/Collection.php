<?php

declare(strict_types=1);

namespace Domains\Catalogue\Results;

use TetherPHP\framework\Interfaces\DomainResult;

/**
 * A Result named for its shape rather than for an operation, so that nothing
 * can find it by guessing at the Action's name.
 */
final readonly class Collection implements DomainResult
{
    /**
     * @param list<array<string, mixed>> $records
     */
    public function __construct(public array $records)
    {
    }
}
