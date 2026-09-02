<?php

namespace TetherPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;

class GlobalFunctionsTest extends TestCase
{
    /**
     * core_dir() is resolved relative to the helper file so that it keeps working
     * once the package is installed under vendor/, where package_root() alone
     * cannot be assumed to sit above a src/ directory.
     */
    public function testCoreDirPointsAtTheShippedFrameworkDirectory(): void
    {
        $this->assertDirectoryExists(core_dir());
        $this->assertFileExists(core_dir() . '/Stubs/Command.txt');
    }

    public function testCoreViewsPointsAtTheShippedErrorViews(): void
    {
        $this->assertFileExists(core_views() . 'errors/404.php');
    }

    public function testPackageRootContainsTheComposerManifest(): void
    {
        $this->assertFileExists(package_root() . '/composer.json');

        $manifest = json_decode(file_get_contents(package_root() . '/composer.json'), true);

        $this->assertSame('dillonsmart/tetherphp-core', $manifest['name']);
    }
}
