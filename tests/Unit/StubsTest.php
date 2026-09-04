<?php

namespace TetherPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;

class StubsTest extends TestCase
{
    private function stub(string $name): string
    {
        return file_get_contents(core_dir() . "/Stubs/{$name}.txt") ?: '';
    }

    public function testEveryStubIsReachableThroughCoreDir(): void
    {
        foreach (['Action', 'Command', 'Domain', 'Responder', 'View'] as $stub) {
            $this->assertFileExists(core_dir() . "/Stubs/{$stub}.txt");
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
     * The Responder stub used to declare `: string` with its only return
     * commented out, so every generated feature fataled on first request with
     * "Return value must be of type string, none returned".
     */
    public function testResponderStubActuallyReturnsSomething(): void
    {
        $stub = $this->stub('Responder');

        $this->assertMatchesRegularExpression('/\breturn\s+\$this->view\(/', $stub);
        $this->assertStringNotContainsString('// return', $stub);
    }

    public function testResponderStubRendersTheViewMakeFeatureGenerates(): void
    {
        $this->assertStringContainsString("pages.{{viewName}}.index", $this->stub('Responder'));
    }

    /**
     * A placeholder no generator substitutes would be emitted literally into the
     * developer's file, so the set of placeholders is part of the contract.
     */
    public function testStubsUseOnlyKnownPlaceholders(): void
    {
        $known = ['{{className}}', '{{commandName}}', '{{viewName}}'];

        foreach (['Action', 'Command', 'Domain', 'Responder', 'View'] as $stub) {
            preg_match_all('/\{\{[a-zA-Z]+\}\}/', $this->stub($stub), $matches);

            foreach (array_unique($matches[0]) as $placeholder) {
                $this->assertContains($placeholder, $known, "{$stub}.txt uses unknown placeholder {$placeholder}");
            }
        }
    }
}
