<?php

declare(strict_types=1);

namespace TetherPHP\framework\Interfaces;

/**
 * Marks the value a Domain returns.
 *
 * Domains used to return `array<string, mixed>`, which the Responder handed
 * straight to the view for extract(). The keys of that array were the view's
 * variable names, so renaming a variable in a template changed the signature of
 * a business-logic class — the coupling the Responder exists to absorb.
 *
 * A result is a value object owned by the Domain and named in the Domain's own
 * terms. Translating it into what a template needs is the Responder's job, and
 * the only place that knowledge belongs.
 *
 * The interface declares nothing on purpose. Its whole job is to give
 * `Domain::handle()` and `Action::respond()` a type that is neither `array` nor
 * `object`, so the pipeline stays traceable by reading it.
 */
interface DomainResult
{
}
