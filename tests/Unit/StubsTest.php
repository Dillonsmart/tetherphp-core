<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;

class StubsTest extends TestCase
{
    /**
     * Every stub the make:* commands render.
     *
     * `make:feature` and `make:resource` share this set: a feature is a
     * resource with one operation, so there is no separate flat set of stubs
     * for features any more. `Command.txt` is the odd one out because a console
     * command is not an ADR triple.
     *
     * @var list<string>
     */
    private const array STUBS = [
        'Command',
        'Action',
        'Domain',
        'DomainWithInput',
        'ResultPage',
        'ResultCollection',
        'ResultRecord',
        'ResultWritten',
        'ResponderPage',
        'ResponderCollection',
        'ResponderRecord',
        'ResponderRedirect',
        'ViewPage',
        'ViewIndex',
        'ViewShow',
        'ViewForm',
    ];

    /** @var list<string> the stubs that make up an ADR triple */
    private const array TRIPLE_STUBS = [
        'Action',
        'Domain',
        'DomainWithInput',
        'ResultPage',
        'ResultCollection',
        'ResultRecord',
        'ResultWritten',
        'ResponderPage',
        'ResponderCollection',
        'ResponderRecord',
        'ResponderRedirect',
    ];

    private function stub(string $name): string
    {
        return file_get_contents(core_dir() . "/Stubs/{$name}.txt") ?: '';
    }

    public function testEveryStubIsReachableThroughCoreDir(): void
    {
        foreach (self::STUBS as $stub) {
            $this->assertFileExists(core_dir() . "/Stubs/{$stub}.txt");
        }
    }

    /**
     * The flat stubs are gone, not deprecated. `make:feature` used to write
     * `app/Actions/Blog.php` while `make:resource` wrote `app/Actions/Post/Index.php`
     * — two layouts for one concept, and Principle 4 says adding a second way
     * means removing the first.
     */
    public function testThereIsNoFlatStubSetLeftBehind(): void
    {
        foreach (['Result', 'Responder', 'View'] as $flat) {
            $this->assertFileDoesNotExist(
                core_dir() . "/Stubs/{$flat}.txt",
                "{$flat}.txt is the old flat stub — features and resources share one set now",
            );
        }
    }

    /**
     * The command stub used to hardcode 'tetherphp:command', so every generated
     * command claimed the same name and collided in the registry.
     */
    public function testCommandStubTakesItsCommandNameFromAPlaceholder(): void
    {
        $stub = $this->stub('Command');

        $this->assertStringContainsString('{{commandName}}', $stub);
        $this->assertStringNotContainsString('tetherphp:command', $stub);
    }

    public function testCommandStubDeclaresTheGeneratedClass(): void
    {
        $this->assertStringContainsString('class {{className}} extends Command', $this->stub('Command'));
    }

    public function testCommandStubIsGeneratedIntoTheCommandsNamespace(): void
    {
        $this->assertStringContainsString('namespace Commands;', $this->stub('Command'));
    }

    /**
     * Every feature owns a namespace, so nothing in a triple is declared at the
     * top level of `Actions\`, `Domains\` or `Responders\`.
     */
    public function testEveryTripleStubIsNamespacedUnderItsFeature(): void
    {
        $namespaces = [
            'Action' => 'namespace Actions\\{{feature}};',
            'Domain' => 'namespace Domains\\{{feature}};',
            'DomainWithInput' => 'namespace Domains\\{{feature}};',
            'ResultPage' => 'namespace Domains\\{{feature}}\\Results;',
            'ResultCollection' => 'namespace Domains\\{{feature}}\\Results;',
            'ResultRecord' => 'namespace Domains\\{{feature}}\\Results;',
            'ResultWritten' => 'namespace Domains\\{{feature}}\\Results;',
            'ResponderPage' => 'namespace Responders\\{{feature}};',
            'ResponderCollection' => 'namespace Responders\\{{feature}};',
            'ResponderRecord' => 'namespace Responders\\{{feature}};',
            'ResponderRedirect' => 'namespace Responders\\{{feature}};',
        ];

        foreach ($namespaces as $stub => $namespace) {
            $this->assertStringContainsString($namespace, $this->stub($stub));
        }
    }

    /**
     * Results are nested under the feature rather than gathered in one
     * top-level bucket, so a feature is one directory under `Domains/` and not
     * two.
     */
    public function testResultsAreNestedUnderTheFeatureNotInASharedBucket(): void
    {
        foreach (['ResultPage', 'ResultCollection', 'ResultRecord', 'ResultWritten'] as $stub) {
            $this->assertStringNotContainsString('namespace Domains\\Results;', $this->stub($stub));
        }

        foreach (['Domain', 'DomainWithInput', 'ResponderPage', 'ResponderRecord'] as $stub) {
            $this->assertStringContainsString(
                'use Domains\\{{feature}}\\Results\\{{result}};',
                $this->stub($stub),
            );
        }
    }

    /**
     * `Domains\Domain::handle()` is declared with no parameters and PHP will
     * not let an override add a required one, so a domain that needs an id or a
     * payload has to take it through its constructor. A stub that generated
     * `handle(string $id)` would fatal on autoload.
     */
    public function testADomainTakesItsInputThroughTheConstructor(): void
    {
        $stub = $this->stub('DomainWithInput');

        $this->assertStringContainsString('public function __construct(', $stub);
        $this->assertStringContainsString('public function handle(): {{result}}', $stub);
        $this->assertDoesNotMatchRegularExpression('/function handle\([^)]+\)/', $stub);
    }

    /**
     * The Responder stub used to declare `: string` with its only return
     * commented out, so every generated feature fataled on first request with
     * "Return value must be of type string, none returned".
     */
    public function testRenderingRespondersActuallyReturnSomething(): void
    {
        foreach (['ResponderPage', 'ResponderCollection', 'ResponderRecord'] as $stub) {
            $this->assertMatchesRegularExpression('/\breturn\s+\$this->view\(/', $this->stub($stub));
            $this->assertStringNotContainsString('// return', $this->stub($stub));
        }
    }

    public function testRenderingRespondersRenderThePageTheGeneratorWrote(): void
    {
        foreach (['ResponderPage', 'ResponderCollection', 'ResponderRecord'] as $stub) {
            $this->assertStringContainsString("pages.{{viewName}}.{{page}}", $this->stub($stub));
        }
    }

    /**
     * Domains used to return array<string, mixed> straight into the Responder,
     * which handed it to extract(). The array's keys were the view's variable
     * names, so a template renamed a business-logic class's return shape.
     */
    public function testDomainStubsReturnAResultRatherThanAnArray(): void
    {
        foreach (['Domain', 'DomainWithInput'] as $stub) {
            $this->assertStringContainsString('public function handle(): {{result}}', $this->stub($stub));
            $this->assertStringNotContainsString('): array', $this->stub($stub));
        }
    }

    public function testResultStubsAreValueObjectsMarkedAsADomainResult(): void
    {
        foreach (['ResultPage', 'ResultCollection', 'ResultRecord', 'ResultWritten'] as $stub) {
            $content = $this->stub($stub);

            $this->assertStringContainsString('final readonly class {{result}} implements DomainResult', $content);
            $this->assertStringContainsString('use TetherPHP\\framework\\Interfaces\\DomainResult;', $content);
        }
    }

    /**
     * The Responder is the only place a view's variables may be named. A stub
     * that forwarded its argument to view() unchanged would put the template
     * back in charge of the domain's shape.
     */
    public function testRespondersTranslateTheResultIntoViewData(): void
    {
        foreach (['ResponderPage', 'ResponderCollection', 'ResponderRecord'] as $stub) {
            $content = $this->stub($stub);

            $this->assertStringContainsString('__invoke({{result}} $result)', $content);
            $this->assertDoesNotMatchRegularExpression('/view\([^)]*,\s*\$result\s*[,)]/', $content);
        }
    }

    /**
     * A write answers with a redirect rather than a page — Post/Redirect/Get,
     * so refreshing after saving does not submit the form again. 303 rather
     * than 302 because only 303 is defined to make the next request a GET.
     */
    public function testAWriteRespondsWithASeeOtherRedirect(): void
    {
        $this->assertStringContainsString(
            'return Response::redirect({{redirect}}, 303);',
            $this->stub('ResponderRedirect'),
        );
    }

    /**
     * The generated update and delete forms are the reason OverridesMethod
     * exists: a browser form can only send GET or POST.
     */
    public function testTheGeneratedFormsDeclareTheVerbTheyMean(): void
    {
        $this->assertStringContainsString('name="_method" value="PUT"', $this->stub('ViewForm'));
        $this->assertStringContainsString('name="_method" value="DELETE"', $this->stub('ViewShow'));
    }

    public function testTheGeneratedFormsCarryACsrfToken(): void
    {
        foreach (['ViewForm', 'ViewShow'] as $stub) {
            $this->assertStringContainsString('name="csrf_token"', $this->stub($stub));
        }
    }

    /**
     * The index page is the one that reads the query string, which is the
     * whole reason a list can paginate or filter.
     */
    public function testTheIndexResponderPassesTheQueryStringToItsView(): void
    {
        $this->assertStringContainsString("'query' => \$this->request->query,", $this->stub('ResponderCollection'));
    }

    /**
     * A Result is named for its shape and shared by every operation of the
     * feature that answers the same way. There are only three answers a CRUD
     * domain gives, and there used to be seven classes carrying them — `Show`
     * and `Edit` differing by their class name and nothing else.
     */
    public function testResultsAreNamedForTheirShapeNotTheOperation(): void
    {
        foreach (['ResultPage', 'ResultCollection', 'ResultRecord', 'ResultWritten'] as $stub) {
            $content = $this->stub($stub);

            $this->assertStringContainsString('class {{result}} implements DomainResult', $content);
            $this->assertStringNotContainsString('class {{operation}}', $content);
        }
    }

    /**
     * A placeholder no generator substitutes would be emitted literally into the
     * developer's file, so the set of placeholders is part of the contract.
     */
    public function testStubsUseOnlyKnownPlaceholders(): void
    {
        $known = [
            '{{className}}',
            '{{commandName}}',
            '{{feature}}',
            '{{operation}}',
            '{{result}}',
            '{{viewName}}',
            '{{page}}',
            '{{uri}}',
            '{{todo}}',
            '{{domainArguments}}',
            '{{domainProperties}}',
            '{{resultArguments}}',
            '{{redirect}}',
        ];

        foreach (self::STUBS as $stub) {
            preg_match_all('/\{\{[a-zA-Z]+\}\}/', $this->stub($stub), $matches);

            foreach (array_unique($matches[0]) as $placeholder) {
                $this->assertContains($placeholder, $known, "{$stub}.txt uses unknown placeholder {$placeholder}");
            }
        }
    }

    /**
     * `{{className}}` is the placeholder the flat stubs used. Only the console
     * command stub still has a single class name to substitute; everything in a
     * triple is named by its feature and its operation.
     */
    public function testOnlyTheCommandStubStillUsesAClassNamePlaceholder(): void
    {
        foreach (self::TRIPLE_STUBS as $stub) {
            $this->assertStringNotContainsString('{{className}}', $this->stub($stub));
        }
    }
}
