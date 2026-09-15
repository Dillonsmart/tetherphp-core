<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Commands\Command;
use TetherPHP\framework\Commands\MakeCommand;
use TetherPHP\framework\Modules\Input;

/**
 * Both names a generated command carries come from the one argument given.
 *
 * The derivation had no test of its own — the placeholder contract was pinned,
 * the table of inputs to outputs was not — and `make:command db:schema`
 * wrote a class called `Db:schemaCommand`, which PHP cannot parse and the
 * registry then reported as unloadable. The framework's own commands are
 * namespaced with a colon, so an application's should be able to be.
 */
class MakeCommandTest extends TestCase
{
    /** @var list<string> */
    private array $written = [];

    protected function tearDown(): void
    {
        foreach ($this->written as $file) {
            @unlink($file);
        }

        $this->written = [];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function names(): iterable
    {
        yield 'kebab' => ['send-welcome-email', 'SendWelcomeEmailCommand', 'send-welcome-email'];
        yield 'pascal' => ['RunNightlyReport', 'RunNightlyReportCommand', 'run-nightly-report'];
        yield 'redundant suffix' => ['DeployCommand', 'DeployCommand', 'deploy'];
        yield 'namespaced' => ['db:schema', 'DbSchemaCommand', 'db:schema'];
        yield 'namespaced, cased' => ['Db:SchemaCommand', 'DbSchemaCommand', 'db:schema'];
        yield 'namespaced, kebab tail' => ['cache:clear-all', 'CacheClearAllCommand', 'cache:clear-all'];
    }

    #[DataProvider('names')]
    public function testBothNamesDeriveFromTheOneArgument(string $given, string $class, string $command): void
    {
        $file = app_dir() . "/Commands/{$class}.php";
        $this->written[] = $file;

        ob_start();
        $status = new MakeCommand(Input::fromTokens([$given]))->execute();
        $output = (string) ob_get_clean();

        $this->assertSame(Command::COMMAND_SUCCESS, $status, $output);
        $this->assertFileExists($file);

        $source = (string) file_get_contents($file);
        $this->assertStringContainsString("class {$class} extends Command", $source);
        $this->assertStringContainsString("public string \$command = '{$command}';", $source);
        $this->assertStringContainsString("php tether {$command}", $output);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'only the suffix' => ['Command'];
        yield 'only a colon' => [':'];
        yield 'a slash' => ['db/schema'];
        yield 'a space' => ['db schema'];
    }

    #[DataProvider('invalidNames')]
    public function testANameThatCannotBecomeAClassIsRefused(string $given): void
    {
        ob_start();
        $status = new MakeCommand(Input::fromTokens([$given]))->execute();
        ob_end_clean();

        $this->assertSame(Command::COMMAND_INVALID_ARGUMENT, $status);
        $this->assertSame([], glob(app_dir() . '/Commands/*:*') ?: []);
    }
}
