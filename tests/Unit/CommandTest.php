<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Commands\Command;
use TetherPHP\framework\Modules\Input;

/**
 * A command that declares two arguments and an option, to exercise the binding
 * a real command relies on.
 */
final class TwoArgumentCommand extends Command
{
    public string $command = 'fixture:two';

    public string $description = 'Takes two arguments';

    /** @var array<string, string> */
    protected array $arguments = [
        'name' => 'The first',
        'kind' => 'The second',
    ];

    /** @var array<string, string> */
    protected array $options = [
        'force' => 'Skip the prompt',
    ];
}

class CommandTest extends TestCase
{
    private function command(string ...$tokens): TwoArgumentCommand
    {
        return new TwoArgumentCommand(Input::fromTokens(array_values($tokens)));
    }

    public function testArgumentsBindInTheOrderTheyAreDeclared(): void
    {
        $command = $this->command('blog', 'page');

        $this->assertSame('blog', $command->argument('name'));
        $this->assertSame('page', $command->argument('kind'));
    }

    public function testAnArgumentThatWasNotSuppliedIsEmpty(): void
    {
        $this->assertSame('', $this->command()->argument('name'));
    }

    /**
     * Options are named, so they do not shift the arguments after them. Before
     * the parser existed they did: everything was positional, so
     * `make:feature --force Blog` bound '--force' to the name.
     */
    public function testAnOptionDoesNotConsumeAnArgumentPosition(): void
    {
        $command = $this->command('--force', 'blog', 'page');

        $this->assertSame('blog', $command->argument('name'));
        $this->assertSame('page', $command->argument('kind'));
        $this->assertTrue($command->hasOption('force'));
    }

    /**
     * Asking for an argument the command does not declare is a bug in the
     * command. array_search() used to return false for it and that false was
     * used as an array index.
     */
    public function testAskingForAnUndeclaredArgumentThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'colour' is not declared by command 'fixture:two'");

        $this->command('blog')->argument('colour');
    }

    public function testUsageIsBuiltFromWhatTheCommandDeclares(): void
    {
        $this->assertSame('tether fixture:two <name> <kind> [--force]', $this->command()->usage());
    }

    public function testACommandWithNoInputStillWorks(): void
    {
        $command = new TwoArgumentCommand();

        $this->assertSame('', $command->argument('name'));
        $this->assertFalse($command->hasOption('force'));
    }
}
