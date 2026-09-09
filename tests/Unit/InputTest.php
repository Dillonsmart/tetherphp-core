<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Modules\Input;

/**
 * Nothing parsed options before this class existed: `bin/tether` passed a
 * literal empty array for them, so `boilerplate:clear --force` had to scan the
 * raw argument list for the string '--force' to find its own flag.
 */
class InputTest extends TestCase
{
    public function testPositionalArgumentsKeepTheirOrder(): void
    {
        $input = Input::fromTokens(['blog', 'post']);

        $this->assertSame(['blog', 'post'], $input->arguments());
        $this->assertSame('blog', $input->argumentAt(0));
        $this->assertSame('post', $input->argumentAt(1));
        $this->assertNull($input->argumentAt(2));
    }

    public function testReadsAnOptionWithAValue(): void
    {
        $input = Input::fromTokens(['--method=POST']);

        $this->assertSame('POST', $input->option('method'));
        $this->assertTrue($input->hasOption('method'));
    }

    public function testAnOptionWithoutAValueIsAFlag(): void
    {
        $input = Input::fromTokens(['--force']);

        $this->assertTrue($input->hasOption('force'));
        $this->assertSame('', $input->option('force'));
    }

    public function testAMissingOptionReturnsTheDefault(): void
    {
        $input = Input::fromTokens([]);

        $this->assertFalse($input->hasOption('port'));
        $this->assertNull($input->option('port'));
        $this->assertSame('8000', $input->option('port', '8000'));
    }

    public function testShortOptionsWorkTheSameWay(): void
    {
        $input = Input::fromTokens(['-f', '-p=90']);

        $this->assertTrue($input->hasOption('f'));
        $this->assertSame('90', $input->option('p'));
    }

    /**
     * The whole point: an option must not be mistaken for an argument, which is
     * what happened when everything was positional.
     */
    public function testOptionsAreNotCountedAsArguments(): void
    {
        $input = Input::fromTokens(['blog', '--force', '--method=POST']);

        $this->assertSame(['blog'], $input->arguments());
        $this->assertSame(['force' => '', 'method' => 'POST'], $input->options());
    }

    public function testOptionsMayComeBeforeArguments(): void
    {
        $input = Input::fromTokens(['--force', 'blog']);

        $this->assertSame(['blog'], $input->arguments());
        $this->assertTrue($input->hasOption('force'));
    }

    public function testAValueMayContainAnEqualsSign(): void
    {
        $this->assertSame('k=v', Input::fromTokens(['--dsn=k=v'])->option('dsn'));
    }

    public function testADoubleDashStopsOptionParsing(): void
    {
        $input = Input::fromTokens(['--force', '--', '--not-an-option', '-5']);

        $this->assertSame(['--not-an-option', '-5'], $input->arguments());
        $this->assertSame(['force' => ''], $input->options());
    }

    /**
     * A bare dash is a conventional argument — it means stdin — and must not be
     * read as an option with an empty name.
     */
    public function testABareDashIsAnArgument(): void
    {
        $this->assertSame(['-'], Input::fromTokens(['-'])->arguments());
    }
}
