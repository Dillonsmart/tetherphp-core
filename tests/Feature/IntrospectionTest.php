<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Commands\Command;
use TetherPHP\framework\Commands\ContextCommand;
use TetherPHP\framework\Commands\ExplainCommand;
use TetherPHP\framework\Commands\InspectCommand;
use TetherPHP\framework\Commands\RoutesCommand;
use TetherPHP\framework\Modules\Input;

/**
 * The commands that answer questions about an application rather than change
 * it, run against the fixture application and the fixture route table that
 * tests/bootstrap.php links into place.
 *
 * Principle 6 says a runtime feature is not finished until the tooling can show
 * it — which makes the tooling's output part of the framework's contract, and
 * worth testing like one.
 */
class IntrospectionTest extends TestCase
{
    /**
     * @param class-string<Command> $command
     */
    private function capture(string $command, string ...$tokens): string
    {
        ob_start();
        new $command(Input::fromTokens(array_values($tokens)))->execute();

        return (string) ob_get_clean();
    }

    /**
     * @param class-string<Command> $command
     */
    private function statusOf(string $command, string ...$tokens): int
    {
        ob_start();
        $status = new $command(Input::fromTokens(array_values($tokens)))->execute();
        ob_end_clean();

        return $status;
    }

    public function testRoutesListsEveryRegisteredRoute(): void
    {
        $output = $this->capture(RoutesCommand::class);

        $this->assertStringContainsString('/greet', $output);
        $this->assertStringContainsString('/greet/{name}', $output);
        $this->assertStringContainsString('POST', $output);
        $this->assertStringContainsString('dynamic', $output);
    }

    /**
     * A route pointing at a class that is missing or not routable is a 500 that
     * only shows up when someone requests it. The table says so first.
     */
    public function testRoutesMarksARouteThatWouldFail(): void
    {
        $output = $this->capture(RoutesCommand::class);

        $this->assertStringContainsString('class not found', $output);
        $this->assertStringContainsString('does not implement ActionInterface', $output);
    }

    public function testRoutesCanBeFilteredByMethod(): void
    {
        $output = $this->capture(RoutesCommand::class, '--method=post');

        $this->assertStringContainsString('/save', $output);
        $this->assertStringNotContainsString('/greet', $output);
    }

    public function testExplainResolvesAStaticRoute(): void
    {
        $output = $this->capture(ExplainCommand::class, '/greet');

        $this->assertStringContainsString('GET /greet', $output);
        $this->assertStringContainsString('static', $output);
        $this->assertStringContainsString('Greet', $output);
    }

    public function testExplainNamesTheParametersADynamicRouteCaptures(): void
    {
        $output = $this->capture(ExplainCommand::class, '/greet/ada');

        $this->assertStringContainsString('dynamic', $output);
        $this->assertStringContainsString("params['name'] = 'ada'", $output);
    }

    /**
     * Request lowercases the URI through a property hook, so explaining
     * /GREET/ADA has to describe the request that would actually be made.
     */
    public function testExplainLowercasesTheUriTheWayARequestDoes(): void
    {
        $output = $this->capture(ExplainCommand::class, '/GREET/ADA');

        $this->assertStringContainsString('/greet/ada', $output);
        $this->assertStringContainsString("params['name'] = 'ada'", $output);
    }

    public function testExplainDropsTheQueryStringBeforeMatching(): void
    {
        $output = $this->capture(ExplainCommand::class, '/greet?utm_source=x');

        $this->assertStringContainsString('query string dropped', $output);
        $this->assertStringContainsString('static', $output);
    }

    public function testExplainSaysWhenNothingMatches(): void
    {
        $this->assertStringContainsString('no match', $this->capture(ExplainCommand::class, '/nothing-here'));
    }

    public function testExplainSaysWhenTheActionIsNotRoutable(): void
    {
        $this->assertStringContainsString(
            'does not implement ActionInterface',
            $this->capture(ExplainCommand::class, '/nope'),
        );
    }

    public function testExplainResolvesADifferentMethod(): void
    {
        $output = $this->capture(ExplainCommand::class, '/save', '--method=post');

        $this->assertStringContainsString('POST /save', $output);
        $this->assertStringContainsString('static', $output);
    }

    public function testExplainNeedsAUri(): void
    {
        $this->assertSame(
            Command::COMMAND_INVALID_ARGUMENT,
            $this->statusOf(ExplainCommand::class),
        );
    }

    public function testInspectIdentifiesAnAction(): void
    {
        $output = $this->capture(InspectCommand::class, \TetherPHP\Tests\Fixtures\app\Actions\Greet::class);

        $this->assertStringContainsString('Action', $output);
        $this->assertStringContainsString('ActionInterface', $output);
    }

    public function testInspectReportsAClassItCannotFind(): void
    {
        $this->assertSame(
            Command::COMMAND_ERROR,
            $this->statusOf(InspectCommand::class, 'NoSuchClassAnywhere'),
        );
    }

    public function testContextIsValidJson(): void
    {
        $context = json_decode($this->capture(ContextCommand::class), true);

        $this->assertIsArray($context);
        $this->assertSame('tetherphp', $context['framework']);
    }

    /**
     * The done-when for this command: an agent handed only its output should be
     * able to say where a new feature's files go and which route would clash.
     */
    public function testContextCarriesWhatAnAgentWouldNeed(): void
    {
        $context = json_decode($this->capture(ContextCommand::class), true);

        $this->assertIsArray($context);

        $this->assertSame('app/Actions', $context['conventions']['directories']['actions']);
        $this->assertSame('Domains\\Results', $context['conventions']['namespaces']['results']);
        $this->assertContains('Responder', $context['pipeline']);

        $uris = array_column($context['routes'], 'uri');
        $this->assertContains('/greet/{name}', $uris);

        $commands = array_column($context['commands'], 'name');
        $this->assertContains('make:feature', $commands);

        $this->assertNotSame([], $context['features']);
    }

    public function testContextMarksARouteThatWouldNotResolve(): void
    {
        $context = json_decode($this->capture(ContextCommand::class), true);

        $this->assertIsArray($context);

        $routes = array_column($context['routes'], 'resolves', 'uri');

        $this->assertFalse($routes['/broken']);
        $this->assertTrue($routes['/greet']);
    }

    public function testContextCanBePrettyPrinted(): void
    {
        $this->assertStringContainsString("\n    ", $this->capture(ContextCommand::class, '--pretty'));
    }
}
