<?php

declare(strict_types=1);

namespace TetherPHP\framework\Requests;

use TetherPHP\framework\Interfaces\RequestInterface;
use TetherPHP\framework\Sessions\Session;

class Request implements RequestInterface
{
    public string $method {
        get {
            return $this->method;
        }
        set {
            $this->method = strtoupper($value);
        }
    }
    public string $uri {
        get {
            return $this->uri;
        }
        set {
            $this->uri = strtolower($value);
        }
    }

    /** @var array<string, mixed> */
    public array $payload {
        get {
            return $this->payload;
        }
        set {
            $this->payload = $value;
        }
    }

    public float|string $startTime;

    // null until a CsrfToken has been generated for the session
    protected ?string $csrfToken = null;

    /**
     * @throws \Exception
     */
    public function __construct(Session $session, string $method = '', string $uri = '', float|string $startTime = '')
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->startTime = $startTime ?: microtime(true);
        $this->csrfToken = $session->get('csrf_token');

        if(in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $this->validateCsrfToken($this->submittedCsrfToken());
        }
    }

    /**
     * The token a write request presented, if any.
     *
     * PHP only populates $_POST for POST bodies, so reading the token from
     * there alone made PUT, PATCH and DELETE impossible to authorise — they
     * could never present a token and so always failed validation. The header
     * is how those methods, and fetch/XHR clients generally, send it.
     */
    private function submittedCsrfToken(): ?string
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        return is_string($token) ? $token : null;
    }

    /**
     * A request with no token, or one made against a session that never had a
     * token generated, is rejected the same way a mismatched token is — it must
     * not be able to crash its way past validation.
     *
     * @throws \Exception
     */
    public function validateCsrfToken(?string $token): void
    {
        if ($this->csrfToken === null || $token === null || !hash_equals($this->csrfToken, $token)) {
            throw new \Exception('Invalid CSRF token');
        }
    }
}