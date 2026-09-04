<?php

declare(strict_types=1);

namespace TetherPHP\framework\Sessions;

class Session
{
    const int TIMEOUT = 1800; // 30 mins default - TODO make this configurable

    public function __construct()
    {
        $this->start();

        if ($this->isExpired()) {
            // Session was destroyed, so start a new one
            $this->start();
        }

        $this->reinitialize();
    }

    /**
     * Starts the session with hardened cookie settings.
     *
     * These have to be set before the session starts, and PHP's defaults are
     * not safe ones: without HttpOnly the cookie is readable by any script on
     * the page, and without SameSite it is sent on cross-site requests, which
     * is the hole CSRF tokens exist to cover.
     */
    private function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        if (!headers_sent()) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                // only promise Secure when the request actually arrived over TLS,
                // or the cookie is dropped in local development
                'secure' => ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off',
            ]);
        }

        session_start();
    }

    /**
     * Issues a new session id, keeping the session's contents.
     *
     * Call this whenever a session changes privilege — after a login above all.
     * Without it an attacker who can plant a session id keeps access after the
     * victim authenticates.
     */
    public function regenerateId(bool $deleteOldSession = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id($deleteOldSession);
            $_SESSION['SESSION_ID'] = session_id();
        }
    }

    public function reinitialize(): void
    {
        $this->startTime();
        $this->setSessionId();
        $this->updateLastActivity();
    }

    public function setSessionId(): void
    {
        if (!isset($_SESSION['SESSION_ID'])) {
            $_SESSION['SESSION_ID'] = session_id();
        }
    }

    public function getSessionId(): string
    {
        $id = $_SESSION['SESSION_ID'] ?? session_id();

        return is_string($id) ? $id : '';
    }

    public function startTime(): void
    {
        if (!isset($_SESSION['start_time'])) {
            $_SESSION['start_time'] = time();
        }
    }

    public function updateLastActivity(): void
    {
        $_SESSION['last_activity'] = time();
    }

    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function destroy(): void
    {
        session_unset();
        session_destroy();
    }

    public function isExpired(): bool
    {
        $now = time();

        // session contents are whatever was last written there; a corrupted or
        // tampered value must not reach arithmetic
        $startTime = is_int($_SESSION['start_time'] ?? null) ? $_SESSION['start_time'] : $now;
        $lastActivity = is_int($_SESSION['last_activity'] ?? null) ? $_SESSION['last_activity'] : $now;
        $timeout = self::TIMEOUT;

        if (($now - $startTime > $timeout) || ($now - $lastActivity > $timeout)) {
            $this->destroy();
            return true;
        }

        return false;
    }
}