<?php

declare(strict_types=1);

namespace Domains\Catalogue;

/**
 * A Domain whose handle() declares nothing useful, so the introspection
 * commands have to fall back to the naming convention.
 */
class Untyped
{
    public function handle(): mixed
    {
        return null;
    }
}
