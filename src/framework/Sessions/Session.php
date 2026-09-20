<?php

declare(strict_types=1);

namespace TetherPHP\framework\Sessions;

class Session
{
    const int TIMEOUT = 1800; // 30 mins default - TODO make this configurable

    private bool $started = false;

    /**
     * Whether to believe `X-Forwarded-Proto: https` when deciding the cookie's
     * Secure flag.
     *
     * Behind Caddy, nginx or a load balancer that terminates TLS, PHP sees a
     * plain HTTP request and `$_SERVER['HTTPS']` is empty, so the session
     * cookie went out without Secure on exactly the deployments that had TLS.
     * The header is the proxy's word for it — and any client can send the
     * header too, so it is only believed when the application says there is a
     * proxy in front of it. Off by default: trusting it blindly would let a
     * plain-HTTP client claim HTTPS.
     */
    public function __construct(private readonly bool $trustForwardedProto = false)
    {
    }

    /**
     * Constructing a Session does nothing. Using one starts it.
     *
     * The constructor used to call session_start(), which meant merely naming
     * a Session had an effect on the world: a cookie was issued, a file was
     * written, and it happened whether or not anything went on to read or
     * write a value. That was invisible while the Kernel built the Session
     * itself, and became a problem the moment applications started composing
     * middleware — `tether routes` and `tether context` cannot report what
     * runs around a request without building the list, and building the list
     * must not start a session from a terminal.
     *
     * It is also the rule the framework already states: a returned value beats
     * a side effect, and a constructor that does work is the least visible
     * place to put it.
     */
    private function ensureStarted(): void
    {
        if ($this->started) {
            return;
        }

        // set first: isExpired() may call destroy(), which comes back through
        // the public API and would otherwise recurse
        $this->started = true;

        $this->start();

        if ($this->isExpired()) {
            // the session was destroyed, so start a new one
            $this->start();
        }

        $this->reinitialize();
    }

    /**
     * Whether anything has actually started this session yet.
     *
     * Lets a caller — the console above all — hold a Session without becoming
     * responsible for one.
     */
    public function hasStarted(): bool
    {
        return $this->started;
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
                'secure' => $this->arrivedOverTls(),
            ]);
        }

        // strict mode refuses a session ID the client made up rather than
        // initialising a session under it, which is the precondition for
        // fixation. PHP's default is off.
        session_start(['use_strict_mode' => true]);
    }

    /**
     * TLS as PHP saw it, or as a trusted proxy reported it.
     */
    private function arrivedOverTls(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';

        if (is_string($https) && $https !== '' && $https !== 'off') {
            return true;
        }

        if (!$this->trustForwardedProto) {
            return false;
        }

        $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';

        return is_string($forwarded) && strtolower(trim($forwarded)) === 'https';
    }

    /**
     * Issues a new session id, keeping the session's contents.
     *
     * Call this whenever a session changes privilege — after a login above all.
     * Without it an attacker who can plant a session id keeps access after the
     * victim authenticates.
     */
    /**
     * Call this when a user's privilege changes — on login, above all — so a
     * session ID that existed before the change is not the one that carries
     * the privilege. The CSRF token goes with it, so a token issued to the
     * anonymous session does not authorise writes for the signed-in one; the
     * next request is issued a fresh one.
     */
    public function regenerateId(bool $deleteOldSession = true): void
    {
        $this->ensureStarted();

        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id($deleteOldSession);
            $_SESSION['SESSION_ID'] = session_id();
            unset($_SESSION['csrf_token']);
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
        $this->ensureStarted();

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
        $this->ensureStarted();

        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();

        $_SESSION[$key] = $value;
    }

    public function destroy(): void
    {
        $this->ensureStarted();

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