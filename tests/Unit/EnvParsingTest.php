<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Modules\Env;

/**
 * Env reads project_root() . '/.env', which in this repository is the fixture
 * .env written by tests/bootstrap.php. These tests drive loadEnv() against a
 * temporary file instead, so parsing can be exercised without moving it.
 */
class EnvParsingTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/tether-env-' . uniqid() . '/.env';
        mkdir(dirname($this->file));
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        @rmdir(dirname($this->file));
    }

    /** @return array<string, string> */
    private function parse(string $contents): array
    {
        file_put_contents($this->file, $contents);

        $env = new class(dirname($this->file)) extends Env {
            public function __construct(private string $root)
            {
                $this->basePath = $this->root;
                $this->loadEnv();
            }

            /** @return array<string, string> */
            public function all(): array
            {
                return $this->envVars;
            }
        };

        return $env->all();
    }

    public function testReadsKeyValuePairs(): void
    {
        $this->assertSame(
            ['APP_NAME' => 'TetherPHP', 'APP_DEBUG' => 'true'],
            $this->parse("APP_NAME=TetherPHP\nAPP_DEBUG=true\n")
        );
    }

    public function testIgnoresCommentsAndBlankLines(): void
    {
        $this->assertSame(
            ['APP_NAME' => 'TetherPHP'],
            $this->parse("# a comment\n\nAPP_NAME=TetherPHP\n\n")
        );
    }

    /**
     * A line with no '=' used to be destructured into two variables, warning
     * about the missing offset and storing a null.
     */
    public function testSkipsALineWithNoAssignment(): void
    {
        $this->assertSame(
            ['APP_NAME' => 'TetherPHP'],
            $this->parse("APP_NAME=TetherPHP\nthis line is not a variable\n")
        );
    }

    public function testStripsAnUnquotedTrailingComment(): void
    {
        $this->assertSame(['APP_ENV' => 'local'], $this->parse("APP_ENV=local # dev only\n"));
    }

    public function testKeepsAHashInsideAQuotedValue(): void
    {
        $this->assertSame(['COLOUR' => '#fdd725'], $this->parse("COLOUR=\"#fdd725\"\n"));
    }

    public function testStripsSurroundingQuotes(): void
    {
        $this->assertSame(['GREETING' => 'hello world'], $this->parse("GREETING='hello world'\n"));
    }

    public function testHandlesWindowsLineEndings(): void
    {
        $this->assertSame(
            ['APP_NAME' => 'TetherPHP', 'APP_DEBUG' => 'false'],
            $this->parse("APP_NAME=TetherPHP\r\nAPP_DEBUG=false\r\n")
        );
    }

    public function testKeepsAnEqualsSignInTheValue(): void
    {
        $this->assertSame(['DSN' => 'k=v;x=y'], $this->parse("DSN=k=v;x=y\n"));
    }
}
