<?php

declare(strict_types=1);

namespace Domains\Catalogue;

use Domains\Catalogue\Results\Collection;
use Domains\Catalogue\Results\Missing;

/**
 * A Domain that can end more than one way, which the framework documents as
 * the way to say so: a different Result type per outcome, and the Responder
 * picking both the view and the status off the type.
 */
class Outcomes
{
    public function handle(): Collection|Missing
    {
        return new Missing();
    }
}
