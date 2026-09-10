<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Commands\Command;
use TetherPHP\framework\Commands\MakeResourceCommand;
use TetherPHP\framework\Modules\Input;

/**
 * The generator that pays for the explicitness.
 *
 * A CRUD resource in this framework is seven Actions, seven Domains, seven
 * Results, seven Responders and four views. That is the correct shape — every
 * one of them is obvious and traceable — and it is not something anyone should
 * type. The roadmap's own sequencing note says so: "Explicitness costs typing;
 * pay for it with generators."
 *
 * These assert the paths, because Principle 2 says a file's location should be
 * derivable from its name: an agent asked to edit the update domain of the
 * Widget resource must be able to go straight to
 * app/Domains/Widget/Update.php without looking.
 *
 * The layout is the same one `make:feature` writes — a directory per feature — so a
 * feature that grows into a resource gains files rather than moving them.
 * MakeFeatureTest asserts the other half of that.
 */
class MakeResourceTest extends TestCase
{
    /** @var list<string> */
    private const array OPERATIONS = ['Index', 'Create', 'Store', 'Show', 'Edit', 'Update', 'Destroy'];

    protected function setUp(): void
    {
        $this->removeGenerated();
    }

    protected function tearDown(): void
    {
        $this->removeGenerated();
    }

    private function generate(string ...$tokens): string
    {
        $command = new MakeResourceCommand(Input::fromTokens(array_values($tokens)));

        ob_start();
        $status = $command->execute();
        $output = (string) ob_get_clean();

        $this->assertSame(Command::COMMAND_SUCCESS, $status, $output);

        return $output;
    }

    public function testEveryOperationGetsAWholeTriple(): void
    {
        $this->generate('Widget', '--uri=/widgets');

        foreach (self::OPERATIONS as $operation) {
            $this->assertFileExists(app_dir() . "/Actions/Widget/{$operation}.php");
            $this->assertFileExists(app_dir() . "/Domains/Widget/{$operation}.php");
            $this->assertFileExists(app_dir() . "/Responders/Widget/{$operation}.php");
        }
    }

    /**
     * Three Results, not seven. There are only three answers a CRUD domain
     * gives — many, one, or "I changed this" — and naming one per operation
     * produced four classes that differed from another by their name and
     * nothing else.
     */
    public function testTheSevenOperationsShareThreeResults(): void
    {
        $this->generate('Widget', '--uri=/widgets');

        foreach (['Collection', 'Record', 'Written'] as $shape) {
            $this->assertFileExists(app_dir() . "/Domains/Widget/Results/{$shape}.php");
        }

        $results = glob(app_dir() . '/Domains/Widget/Results/*.php') ?: [];

        $this->assertCount(3, $results);
    }

    /**
     * Which operations share which shape, asserted where it matters: Show and
     * Edit answer with the same thing, and so do all three writes.
     */
    public function testEachOperationTypeHintsTheShapeItAnswersWith(): void
    {
        $this->generate('Widget', '--uri=/widgets');

        $expected = [
            'Index' => 'Collection',
            'Create' => 'Record',
            'Show' => 'Record',
            'Edit' => 'Record',
            'Store' => 'Written',
            'Update' => 'Written',
            'Destroy' => 'Written',
        ];

        foreach ($expected as $operation => $shape) {
            $domain = (string) file_get_contents(app_dir() . "/Domains/Widget/{$operation}.php");

            $this->assertStringContainsString("use Domains\\Widget\\Results\\{$shape};", $domain);
            $this->assertStringContainsString("public function handle(): {$shape}", $domain);
        }
    }

    /**
     * Four, not seven: Store, Update and Destroy redirect rather than render.
     */
    public function testOnlyThePagesThatRenderGetAView(): void
    {
        $this->generate('Widget', '--uri=/widgets');

        foreach (['index', 'create', 'show', 'edit'] as $page) {
            $this->assertFileExists(app_dir() . "/Views/pages/widget/{$page}.php");
        }

        $this->assertFileDoesNotExist(app_dir() . '/Views/pages/widget/store.php');
        $this->assertFileDoesNotExist(app_dir() . '/Views/pages/widget/destroy.php');
    }

    public function testEveryGeneratedFileIsValidPhp(): void
    {
        $this->generate('Widget', '--uri=/widgets');

        foreach ($this->generatedFiles() as $file) {
            $output = [];
            $status = 0;
            exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $status);

            $this->assertSame(0, $status, "{$file}: " . implode("\n", $output));
        }
    }

    /**
     * The seven routes are printed, never written. A generator that edited
     * routes/web.php would make the one file a reader has to trust the one
     * file a tool had been at.
     */
    public function testItPrintsTheSevenRoutesForTheRouteTable(): void
    {
        $output = $this->generate('Widget', '--uri=/widgets');

        $expected = [
            "\$router->get('/widgets', Actions\\Widget\\Index::class);",
            "\$router->get('/widgets/create', Actions\\Widget\\Create::class);",
            "\$router->post('/widgets', Actions\\Widget\\Store::class);",
            "\$router->get('/widgets/{id}', Actions\\Widget\\Show::class);",
            "\$router->get('/widgets/{id}/edit', Actions\\Widget\\Edit::class);",
            "\$router->put('/widgets/{id}', Actions\\Widget\\Update::class);",
            "\$router->delete('/widgets/{id}', Actions\\Widget\\Destroy::class);",
        ];

        foreach ($expected as $route) {
            $this->assertStringContainsString($route, $output);
        }
    }

    /**
     * The PUT and DELETE routes it just printed are unreachable from a browser
     * form without the middleware, so saying so is part of the job.
     */
    public function testItSaysWhichMiddlewareTheGeneratedFormsNeed(): void
    {
        $this->assertStringContainsString(
            'Middleware\OverridesMethod',
            $this->generate('Widget', '--uri=/widgets'),
        );
    }

    /**
     * There is no pluraliser, so the URI defaults to the name as given. An
     * application that wants /widgets says so.
     */
    public function testTheUriDefaultsToTheKebabCasedName(): void
    {
        $output = $this->generate('Widget');

        $this->assertStringContainsString("\$router->get('/widget', Actions\\Widget\\Index::class);", $output);
    }

    public function testTheGeneratedRedirectsPointAtTheGivenUri(): void
    {
        $this->generate('Widget', '--uri=/widgets');

        $store = (string) file_get_contents(app_dir() . '/Responders/Widget/Store.php');
        $destroy = (string) file_get_contents(app_dir() . '/Responders/Widget/Destroy.php');

        $this->assertStringContainsString("Response::redirect('/widgets/' . \$result->id, 303)", $store);
        $this->assertStringContainsString("Response::redirect('/widgets', 303)", $destroy);
    }

    /**
     * The Action reads the id off the path and the fields off the body, and
     * hands both to the Domain through its constructor — the only way to give
     * a Domain anything, since handle() takes no parameters.
     */
    public function testTheUpdateActionPassesBothTheIdAndTheBodyToItsDomain(): void
    {
        $this->generate('Widget', '--uri=/widgets');

        $action = (string) file_get_contents(app_dir() . '/Actions/Widget/Update.php');

        $this->assertStringContainsString(
            "new UpdateDomain(\$request->params['id'] ?? '', \$request->payload)",
            $action,
        );
    }

    public function testItRefusesAnEmptyName(): void
    {
        $command = new MakeResourceCommand(Input::fromTokens([]));

        ob_start();
        $status = $command->execute();
        ob_end_clean();

        $this->assertSame(Command::COMMAND_INVALID_ARGUMENT, $status);
    }

    /**
     * writeStub refuses to overwrite, so a second run reports rather than
     * quietly replacing hand-written code.
     */
    public function testItRefusesToOverwriteWhatIsAlreadyThere(): void
    {
        $this->generate('Widget', '--uri=/widgets');

        $command = new MakeResourceCommand(Input::fromTokens(['Widget', '--uri=/widgets']));

        ob_start();
        $status = $command->execute();
        $output = (string) ob_get_clean();

        $this->assertSame(Command::COMMAND_ERROR, $status);
        $this->assertStringContainsString('Already exists', $output);
    }

    /**
     * @return list<string>
     */
    private function generatedFiles(): array
    {
        $files = [];

        foreach ($this->generatedDirectories() as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile()) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function generatedDirectories(): array
    {
        return [
            app_dir() . '/Actions/Widget',
            app_dir() . '/Domains/Widget',
            app_dir() . '/Responders/Widget',
            app_dir() . '/Views/pages/widget',
        ];
    }

    private function removeGenerated(): void
    {
        foreach ($this->generatedDirectories() as $directory) {
            $this->removeDirectory($directory);
        }

        foreach ([app_dir() . '/Domains', app_dir() . '/Responders', app_dir() . '/Views/pages'] as $parent) {
            $this->removeIfEmpty($parent);
        }
    }

    /**
     * The feature directories sit inside app/Domains and app/Responders, which
     * the fixture application does not otherwise have. Removing the feature but
     * leaving those behind would drop two empty directories into the fixtures
     * on every run.
     */
    private function removeIfEmpty(string $directory): void
    {
        if (is_dir($directory) && scandir($directory) === ['.', '..']) {
            rmdir($directory);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
}
