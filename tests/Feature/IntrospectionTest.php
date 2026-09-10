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
     * Matching ignores case; the URI is not rewritten to achieve it.
     *
     * Explain used to lowercase the URI before resolving it, because Request
     * did — and a parameter captured out of a lowercased URI is a lowercased
     * parameter, which is why a slug or a UUID could not be routed. What the
     * command prints has to be the request that will actually be made.
     */
    public function testExplainResolvesTheUriAsSentAndKeepsParameterCase(): void
    {
        $output = $this->capture(ExplainCommand::class, '/GREET/Ada');

        $this->assertStringContainsString('/GREET/Ada', $output);
        $this->assertStringContainsString("params['name'] = 'Ada'", $output);
    }

    /**
     * The query string is not matched on and is no longer thrown away, so the
     * command shows what the Action will receive rather than saying it is gone.
     */
    public function testExplainShowsTheQueryStringTheActionWillReceive(): void
    {
        $output = $this->capture(ExplainCommand::class, '/greet?utm_source=x');

        $this->assertStringContainsString('not matched on', $output);
        $this->assertStringContainsString("query['utm_source'] = 'x'", $output);
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

    /**
     * The gap this closed: middleware was declared in public/index.php, which
     * these commands must not load, so nothing could say what runs around a
     * request. `explain` in particular claimed to show "the path a URI takes"
     * while hiding the first thing that happens to it.
     */
    public function testExplainShowsTheMiddlewareARequestPassesThrough(): void
    {
        $output = $this->capture(ExplainCommand::class, '/greet');

        $this->assertStringContainsString('Middleware', $output);
        $this->assertStringContainsString('AddsHeader', $output);
        $this->assertStringContainsString('VerifyCsrfToken', $output);
    }

    /**
     * Order is the contract — the first declared is the outermost — so the
     * output has to preserve it rather than merely list the classes.
     */
    public function testExplainKeepsTheDeclaredMiddlewareOrder(): void
    {
        $output = $this->capture(ExplainCommand::class, '/greet');

        $this->assertLessThan(
            strpos($output, 'VerifyCsrfToken'),
            strpos($output, 'AddsHeader'),
        );
    }

    /**
     * A request that goes on to 404 still passes through the middleware, so
     * explaining one has to say so.
     */
    public function testExplainShowsMiddlewareEvenWhenNothingMatches(): void
    {
        $output = $this->capture(ExplainCommand::class, '/nothing-here');

        $this->assertStringContainsString('AddsHeader', $output);
        $this->assertStringContainsString('no match', $output);
    }

    public function testRoutesReportsTheMiddlewareEveryRouteGoesThrough(): void
    {
        $output = $this->capture(RoutesCommand::class);

        $this->assertStringContainsString('outermost first', $output);
        $this->assertStringContainsString('VerifyCsrfToken', $output);
    }

    public function testContextCarriesTheMiddleware(): void
    {
        $context = json_decode($this->capture(ContextCommand::class), true);

        $this->assertIsArray($context);
        $this->assertNull($context['middleware']['problem']);
        $this->assertSame(
            [
                'TetherPHP\Tests\Fixtures\app\Middleware\AddsHeader',
                'TetherPHP\framework\Middleware\VerifyCsrfToken',
            ],
            $context['middleware']['names'],
        );
    }

    /**
     * The contract the whole approach rests on: the console builds the list
     * only to read class names off it, so building one must not touch the
     * world. VerifyCsrfToken holds a Session and is in the fixture list
     * precisely to prove it.
     */
    public function testBuildingTheMiddlewareListStartsNoSession(): void
    {
        $before = session_status();

        $this->capture(RoutesCommand::class);

        $this->assertSame($before, session_status(), 'listing middleware must not start a session');
    }

    public function testContextCanBePrettyPrinted(): void
    {
        $this->assertStringContainsString("\n    ", $this->capture(ContextCommand::class, '--pretty'));
    }
}
