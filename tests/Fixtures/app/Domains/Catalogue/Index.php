<?php

declare(strict_types=1);

namespace Domains\Catalogue;

use Domains\Catalogue\Results\Collection;

/**
 * A Domain that declares what it returns.
 *
 * The introspection commands read this signature rather than predicting a
 * Result from the Action's name, so this fixture deliberately returns a class
 * that no naming convention would arrive at: nothing is called
 * `Domains\Catalogue\Results\Index`.
 *
 * It does not extend `Domains\Domain`, which belongs to the skeleton — the
 * reflection under test reads a return type and constructs nothing.
 */
class Index
{
    public function handle(): Collection
    {
        return new Collection([]);
    }
}
