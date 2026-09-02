<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Modules\Console;

class ConsoleTest extends TestCase
{
    /** @var resource */
    private $stream;

    protected function setUp(): void
    {
        // capture registration diagnostics instead of letting them hit stderr
        $stream = fopen('php://memory', 'r+');

        if ($stream === false) {
            $this->fail('could not open a memory stream');
        }

        $this->stream = $stream;
    }

    private function console(string $command = 'help'): Console
    {
        return new Console($command, $this->stream);
    }

    private function diagnostics(): string
    {
        rewind($this->stream);

        return (string) stream_get_contents($this->stream);
    }

    public function testRegistersTheBuiltInCommands(): void
    {
        $commands = $this->console()->commands;

        foreach (['help', 'make:command', 'make:feature', 'boilerplate:clear'] as $expected) {
            $this->assertArrayHasKey($expected, $commands);
        }
    }

    public function testRegistersACommandFromTheApplication(): void
    {
        $this->assertArrayHasKey('fixture:good', $this->console()->commands);
    }

    /**
     * A class in the commands directory that does not extend Command is skipped —
     * but it must say so. Silence here made a misnamed class, a missing psr-4
     * mapping and a file that was never written indistinguishable.
     */
    public function testExplainsWhyAClassWasNotRegistered(): void
    {
        $skipped = implode("\n", $this->console()->skipped());

        $this->assertStringContainsString('NotACommand', $skipped);
        $this->assertStringContainsString('does not extend', $skipped);
    }

    public function testDoesNotRegisterTheClassThatFailedTheCheck(): void
    {
        $this->assertArrayNotHasKey('fixture:bad', $this->console()->commands);
    }

    public function testWritesTheReasonToTheErrorStream(): void
    {
        $this->console();

        $this->assertStringContainsString('NotACommand', $this->diagnostics());
    }

    /**
     * Command is the base class the built-ins extend. It used to be globbed
     * alongside them and reported as failing its own subclass check.
     */
    public function testDoesNotReportTheBaseCommandClassAsSkipped(): void
    {
        $this->assertStringNotContainsString(
            'Commands\\Command does not extend',
            implode("\n", $this->console()->skipped())
        );
    }

    public function testAnUnknownCommandReportsFailure(): void
    {
        ob_start();
        $status = $this->console('no:such:command')->executeCommand();
        ob_end_clean();

        $this->assertSame(1, $status);
    }

    public function testAKnownCommandReportsSuccess(): void
    {
        ob_start();
        $status = $this->console('fixture:good')->executeCommand();
        ob_end_clean();

        $this->assertSame(0, $status);
    }
}
