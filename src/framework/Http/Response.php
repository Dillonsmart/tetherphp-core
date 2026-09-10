<?php

declare(strict_types=1);

namespace TetherPHP\framework\Http;

/**
 * The last stage of the pipeline the framework documents.
 *
 * Until now it had no representation in code: the Kernel returned a string, or
 * echoed an included view, or set a status and exited. Three exits from one
 * method meant a response could not be tested, wrapped or inspected. A response
 * is now a value you can hold, and `send()` is the single place anything is
 * written to the client.
 */
final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $body = '',
        private readonly int $status = 200,
        private readonly array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \RuntimeException when the data cannot be encoded
     */
    public static function json(array $data, int $status = 200): self
    {
        $encoded = json_encode($data);

        if ($encoded === false) {
            throw new \RuntimeException('Could not encode response: ' . json_last_error_msg());
        }

        return new self($encoded, $status, ['Content-Type' => 'application/json']);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    public function body(): string
    {
        return $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->body, $this->status, [...$this->headers, $name => $value]);
    }

    public function withStatus(int $status): self
    {
        return new self($this->body, $status, $this->headers);
    }

    /**
     * Writes the response to the client.
     *
     * The only place in the framework that emits anything. Headers are skipped
     * if something has already sent output, so a stray echo in a view degrades
     * to a wrong status rather than a "headers already sent" warning.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo $this->body;
    }
}
