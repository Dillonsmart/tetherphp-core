<?php

declare(strict_types=1);

namespace TetherPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TetherPHP\framework\Modules\Log;

class LogTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/tether-log-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*.log') ?: [] as $file) {
            unlink($file);
        }

        @rmdir($this->directory);
    }

    /**
     * One entry is one line. A message with a newline in it — an exception
     * that echoes what a visitor sent — would otherwise forge an entry.
     */
    public function testAMessageWithNewlinesStaysOnOneLine(): void
    {
        new Log($this->directory)->error("first line\r\n[2020-01-01 00:00:00] [info] forged entry\nthird");

        $lines = file($this->directory . '/' . date('Y-m-d') . '.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        $this->assertCount(1, $lines);
        $this->assertStringEndsWith('[error] first line [2020-01-01 00:00:00] [info] forged entry third', $lines[0]);
    }

    /**
     * The fallback for an unwritable directory is PHP's own error log, and
     * the message reaches it flattened too — it used to go there raw, before
     * the newlines were stripped.
     */
    public function testTheFallbackLogGetsOneLineToo(): void
    {
        touch($this->directory);
        $errorLog = $this->directory . '.error_log';
        $previous = ini_set('error_log', $errorLog);

        try {
            new Log($this->directory . '/logs')->error("first\n[forged] second");
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
        }

        $lines = file($errorLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        unlink($errorLog);
        unlink($this->directory);

        $this->assertCount(2, $lines);
        $this->assertStringContainsString('cannot create log directory', $lines[0]);
        $this->assertStringEndsWith('[error] first [forged] second', $lines[1]);
    }
}
