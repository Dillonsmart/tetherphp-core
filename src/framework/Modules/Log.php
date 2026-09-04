<?php

declare(strict_types=1);

namespace TetherPHP\framework\Modules;

class Log
{
    private const  LOG_DIR = 'logs/';

    public static function error(string $message): void
    {
        self::writeLog('error', $message);
    }

    public static function info(string $message): void
    {
        self::writeLog('info', $message);
    }

    /**
     * Appends one line to today's log file.
     *
     * mkdir() and file_put_contents() report failure by returning false and
     * raising a warning — they do not throw — so a try/catch here would never
     * fire and an unwritable storage directory would lose every log silently.
     * Failures fall back to PHP's own error log instead.
     */
    private static function writeLog(string $level, string $message): void
    {
        $logDir = storage_dir() . self::LOG_DIR;

        if (!is_dir($logDir) && !@mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            error_log("TetherPHP: cannot create log directory {$logDir}");
            error_log("[{$level}] {$message}");

            return;
        }

        $logFile = $logDir . date('Y-m-d') . '.log';
        $logMessage = '[' . date('Y-m-d H:i:s') . "] [$level] $message" . PHP_EOL;

        // LOCK_EX: concurrent requests append to the same file and would
        // otherwise interleave mid-line
        if (@file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX) === false) {
            error_log("TetherPHP: cannot write to {$logFile}");
            error_log("[{$level}] {$message}");
        }
    }
}