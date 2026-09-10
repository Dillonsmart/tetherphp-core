<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Commands\Command;
use TetherPHP\framework\Commands\MakeActionCommand;
use TetherPHP\framework\Commands\MakeDomainCommand;
use TetherPHP\framework\Commands\MakeFeatureCommand;
use TetherPHP\framework\Commands\MakeResponderCommand;
use TetherPHP\framework\Modules\Input;

/**
 * A feature is a directory, and it grows by addition.
 *
 * `make:feature` used to write five flat files while `make:resource` wrote a
 * directory per resource — two layouts for one concept. The flat one was also a
 * dead end: adding a second route to a feature meant moving four files and
 * rewriting four namespaces, which is a refactor charged for doing exactly the
 * thing a framework should make cheap.
 *
 * The views were already nested. `app/Views/pages/blog/index.php` has been the
 * layout since `make:feature` existed; only the classes disagreed.
 */
class MakeFeatureTest extends TestCase
{
    private const string FEATURE = 'Gadget';

    private const string VIEW = 'gadget';

    protected function setUp(): void
    {
        $this->removeGenerated();
    }

    protected function tearDown(): void
    {
        $this->removeGenerated();
    }

    /**
     * @param class-string<Command> $command
     */
    private function generate(string $command, string ...$tokens): string
    {
        $instance = new $command(Input::fromTokens(array_values($tokens)));

        ob_start();
        $status = $instance->execute();
        $output = (string) ob_get_clean();

        $this->assertSame(Command::COMMAND_SUCCESS, $status, $output);

        return $output;
    }

    public function testAFeatureIsWrittenIntoItsOwnDirectory(): void
    {
        $this->generate(MakeFeatureCommand::class, self::FEATURE);

        $this->assertFileExists(app_dir() . '/Actions/Gadget/Index.php');
        $this->assertFileExists(app_dir() . '/Domains/Gadget/Index.php');
        $this->assertFileExists(app_dir() . '/Domains/Gadget/Results/Page.php');
        $this->assertFileExists(app_dir() . '/Responders/Gadget/Index.php');
        $this->assertFileExists(app_dir() . '/Views/pages/gadget/index.php');
    }

    /**
     * The old layout is gone, not merely unused. Leaving it reachable would be
     * the second way to do one thing that Principle 4 rejects.
     */
    public function testNothingIsWrittenFlat(): void
    {
        $this->generate(MakeFeatureCommand::class, self::FEATURE);

        $this->assertFileDoesNotExist(app_dir() . '/Actions/Gadget.php');
        $this->assertFileDoesNotExist(app_dir() . '/Domains/Gadget.php');
        $this->assertFileDoesNotExist(app_dir() . '/Domains/Results/Gadget.php');
        $this->assertFileDoesNotExist(app_dir() . '/Responders/Gadget.php');
    }

    /**
     * The Result sits beside the Domain that returns it, so one feature owns
     * one directory under `Domains/` rather than two — and it is named for its
     * shape, so a second operation answering the same way reuses it.
     */
    public function testTheResultIsNestedUnderTheFeature(): void
    {
        $this->generate(MakeFeatureCommand::class, self::FEATURE);

        $domain = (string) file_get_contents(app_dir() . '/Domains/Gadget/Index.php');
        $result = (string) file_get_contents(app_dir() . '/Domains/Gadget/Results/Page.php');

        $this->assertStringContainsString('namespace Domains\Gadget\Results;', $result);
        $this->assertStringContainsString('use Domains\Gadget\Results\Page;', $domain);
    }

    /**
     * The point of the whole layout: a second route is a file added next to the
     * first, not four files moved.
     */
    public function testAFeatureGrowsByAdditionRatherThanMigration(): void
    {
        $this->generate(MakeFeatureCommand::class, self::FEATURE);

        $indexBefore = (string) file_get_contents(app_dir() . '/Actions/Gadget/Index.php');

        $this->generate(MakeActionCommand::class, self::FEATURE, 'Show');
        $this->generate(MakeDomainCommand::class, self::FEATURE, 'Show');
        $this->generate(MakeResponderCommand::class, self::FEATURE, 'Show');

        $this->assertFileExists(app_dir() . '/Actions/Gadget/Show.php');
        $this->assertFileExists(app_dir() . '/Domains/Gadget/Show.php');
        $this->assertFileExists(app_dir() . '/Domains/Gadget/Results/Page.php');
        $this->assertFileExists(app_dir() . '/Responders/Gadget/Show.php');
        $this->assertFileExists(app_dir() . '/Views/pages/gadget/show.php');

        $this->assertFileExists(app_dir() . '/Actions/Gadget/Index.php');
        $this->assertSame(
            $indexBefore,
            (string) file_get_contents(app_dir() . '/Actions/Gadget/Index.php'),
            'adding an operation must not touch the ones already there',
        );
    }

    /**
     * A Result is shared, so a second operation of the same shape finds it
     * already written. `writeStub()` treats an existing file as an error, which
     * is right for a class one command owns and wrong for one they share.
     */
    public function testASecondOperationReusesTheSharedResultRatherThanFailing(): void
    {
        $this->generate(MakeFeatureCommand::class, self::FEATURE);
        $output = $this->generate(MakeDomainCommand::class, self::FEATURE, 'Show');

        $this->assertStringContainsString('Reusing', $output);
        $this->assertFileExists(app_dir() . '/Domains/Gadget/Show.php');
        $this->assertFileDoesNotExist(app_dir() . '/Domains/Gadget/Results/Show.php');
    }

    /**
     * `make:action Blog` with no operation is the common case — the first page
     * of a feature — so it means Index rather than failing.
     */
    public function testThePiecemealGeneratorsDefaultToIndex(): void
    {
        $this->generate(MakeActionCommand::class, self::FEATURE);

        $this->assertFileExists(app_dir() . '/Actions/Gadget/Index.php');
    }

    public function testTheRouteItPrintsNamesTheNestedAction(): void
    {
        $output = $this->generate(MakeFeatureCommand::class, self::FEATURE);

        $this->assertStringContainsString("\$router->get('/gadget', Actions\\Gadget\\Index::class);", $output);
    }

    public function testEveryGeneratedFileIsValidPhp(): void
    {
        $this->generate(MakeFeatureCommand::class, self::FEATURE);
        $this->generate(MakeActionCommand::class, self::FEATURE, 'Show');
        $this->generate(MakeDomainCommand::class, self::FEATURE, 'Show');
        $this->generate(MakeResponderCommand::class, self::FEATURE, 'Show');

        foreach ($this->generatedFiles() as $file) {
            $output = [];
            $status = 0;
            exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $status);

            $this->assertSame(0, $status, "{$file}: " . implode("\n", $output));
        }
    }

    /**
     * An Action generated on its own references a Domain and a Responder that
     * may not exist yet. That is legitimate — the other two may be coming — but
     * it must not be silent, and the advice has to name the operation now that
     * there is one.
     */
    public function testAnActionOnItsOwnSaysWhatIsMissingAndHowToMakeIt(): void
    {
        $output = $this->generate(MakeActionCommand::class, self::FEATURE, 'Show');

        $this->assertStringContainsString('Domains\Gadget\Show does not exist yet', $output);
        $this->assertStringContainsString('make:domain Gadget Show', $output);
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
            app_dir() . '/Actions/' . self::FEATURE,
            app_dir() . '/Domains/' . self::FEATURE,
            app_dir() . '/Responders/' . self::FEATURE,
            app_dir() . '/Views/pages/' . self::VIEW,
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
