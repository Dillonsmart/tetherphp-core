<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Commands\Command;
use TetherPHP\framework\Traits\InspectsApplication;

/**
 * `triple()` is protected because nothing outside a command should call it.
 * A test is the exception, and exposing it here beats routing every assertion
 * through a command's rendered output.
 *
 * It extends Command because the trait reports its own failures through
 * `error()`. Console only globs `src/framework/Commands` and `app/Commands`,
 * so nothing here reaches the registry.
 */
final class ExposedInspector extends Command
{
    use InspectsApplication;

    public string $command = 'fixture:inspect';

    public string $description = 'Exposes triple() for the tests';

    /**
     * @return array<string, array{class: string, exists: bool, file: ?string, declared: bool}>
     */
    public function inspect(string $action): array
    {
        return $this->triple($action);
    }
}

/**
 * How the introspection commands find the parts of an ADR triple.
 *
 * The Domain and the Responder are found by name and the output says so. The
 * Result is not: it is read off `Domain::handle()`'s declared return type,
 * because Results are shared by shape — Show and Edit both return
 * `Results\Record` — so there is no Result named after each operation to guess
 * at. Guessing reported "(not found)" for every action in every resource.
 */
class InspectsApplicationTest extends TestCase
{
    private ExposedInspector $inspector;

    protected function setUp(): void
    {
        $this->inspector = new ExposedInspector();
    }

    public function testTheResultComesFromTheDomainsDeclaredReturnType(): void
    {
        $result = $this->inspector->inspect('Actions\Catalogue\Index')['result'];

        $this->assertSame('Domains\Catalogue\Results\Collection', $result['class']);
        $this->assertTrue($result['exists']);
        $this->assertTrue($result['declared']);
    }

    /**
     * The point of reading the declaration: no naming convention would arrive
     * at `Results\Collection` from an Action called `Index`.
     */
    public function testTheDeclaredResultIsOneNoConventionWouldPredict(): void
    {
        $result = $this->inspector->inspect('Actions\Catalogue\Index')['result'];

        $this->assertNotSame('Domains\Catalogue\Results\Index', $result['class']);
    }

    public function testTheDomainAndResponderAreStillFoundByName(): void
    {
        $triple = $this->inspector->inspect('Actions\Catalogue\Index');

        $this->assertSame('Domains\Catalogue\Index', $triple['domain']['class']);
        $this->assertSame('Responders\Catalogue\Index', $triple['responder']['class']);
        $this->assertFalse($triple['domain']['declared']);
        $this->assertFalse($triple['responder']['declared']);
    }

    /**
     * The Action is what the route names, so it is never a guess.
     */
    public function testTheActionIsNeverAGuess(): void
    {
        $this->assertTrue($this->inspector->inspect('Actions\Catalogue\Index')['action']['declared']);
    }

    /**
     * A Domain that can end more than one way says so with a union, which is
     * the framework's own documented pattern — a Result type per outcome, with
     * the Responder picking the view and the status off the type.
     *
     * Reading only a single named type meant every domain written that way
     * fell back to the naming convention and was reported as missing. The
     * website found this: its dev log returns Post|PostNotFound.
     */
    public function testAUnionOfResultTypesIsReadRatherThanGuessedAt(): void
    {
        $result = $this->inspector->inspect('Actions\Catalogue\Outcomes')['result'];

        $this->assertSame(
            'Domains\Catalogue\Results\Collection|Domains\Catalogue\Results\Missing',
            $result['class'],
        );
        $this->assertTrue($result['declared']);
        $this->assertTrue($result['exists']);
    }

    /**
     * A union names several files, so there is no single path to report and
     * explain prints none rather than picking a favourite.
     */
    public function testAUnionReportsNoSingleFile(): void
    {
        $this->assertNull($this->inspector->inspect('Actions\Catalogue\Outcomes')['result']['file']);
    }

    /**
     * A Domain that declares `mixed` says nothing a reader does not already
     * know, so the convention takes over and the output admits it is guessing.
     */
    public function testAnUntypedHandleFallsBackToTheConvention(): void
    {
        $result = $this->inspector->inspect('Actions\Catalogue\Untyped')['result'];

        $this->assertSame('Domains\Catalogue\Results\Untyped', $result['class']);
        $this->assertFalse($result['declared']);
    }

    public function testAMissingDomainFallsBackToTheConvention(): void
    {
        $result = $this->inspector->inspect('Actions\NotThere\Index')['result'];

        $this->assertFalse($result['declared']);
        $this->assertFalse($result['exists']);
    }

    /**
     * An application generated before features had a directory each keeps its
     * Results in one top-level bucket, and these commands exist to report what
     * is actually on disk.
     */
    public function testAFlatActionNameIsReportedTheOldWay(): void
    {
        $result = $this->inspector->inspect('Actions\Home')['result'];

        $this->assertSame('Domains\Results\Home', $result['class']);
        $this->assertFalse($result['declared']);
    }
}
