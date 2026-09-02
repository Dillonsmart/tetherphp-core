<?php

namespace TetherPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Traits\Strings;

class StringsTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        $this->subject = new class {
            use Strings;
        };
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
}
