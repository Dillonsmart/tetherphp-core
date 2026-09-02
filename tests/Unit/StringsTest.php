<?php

namespace TetherPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Traits\Strings;

/**
 * A trait needs a host class to be exercised. A named one keeps the methods
 * visible to static analysis, which an anonymous class does not.
 */
final class StringsHost
{
    use Strings;
}

class StringsTest extends TestCase
{
    private StringsHost $subject;

    protected function setUp(): void
    {
        $this->subject = new StringsHost();
    }

    public function testConvertsHyphenatedNamesToPascalCase(): void
    {
        $this->assertSame('SendWelcomeEmail', $this->subject->toPascalCase('send-welcome-email'));
    }

    public function testConvertsSnakeCaseNamesToPascalCase(): void
    {
        $this->assertSame('SendWelcomeEmail', $this->subject->toPascalCase('send_welcome_email'));
    }

    public function testLeavesAnAlreadyPascalCasedNameUntouched(): void
    {
        $this->assertSame('SendWelcomeEmail', $this->subject->toPascalCase('SendWelcomeEmail'));
    }

    public function testToValidClassNameMatchesPascalCase(): void
    {
        $this->assertSame(
            $this->subject->toPascalCase('user-profile'),
            $this->subject->toValidClassName('user-profile')
        );
    }

    public function testKebabCasesAPascalCasedName(): void
    {
        $this->assertSame('send-welcome-email', $this->subject->toKebabCase('SendWelcomeEmail'));
    }

    public function testLeavesAnAlreadyKebabCasedNameUntouched(): void
    {
        $this->assertSame('send-welcome-email', $this->subject->toKebabCase('send-welcome-email'));
    }

    public function testKebabCasesASnakeCasedName(): void
    {
        $this->assertSame('send-welcome-email', $this->subject->toKebabCase('send_welcome_email'));
    }

    public function testKebabCasesASingleWord(): void
    {
        $this->assertSame('deploy', $this->subject->toKebabCase('Deploy'));
    }

    public function testKeepsARunOfCapitalsTogether(): void
    {
        $this->assertSame('send-httprequest', $this->subject->toKebabCase('SendHTTPRequest'));
    }

    public function testPascalAndKebabInputsProduceTheSameCommandName(): void
    {
        $this->assertSame(
            $this->subject->toKebabCase('send-welcome-email'),
            $this->subject->toKebabCase('SendWelcomeEmail')
        );
    }
}
