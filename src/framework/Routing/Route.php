<?php

declare(strict_types=1);

namespace TetherPHP\framework\Routing;

/**
 * The outcome of matching a request against the route table.
 *
 * Replaces RouteDTO, which signalled "not found" by leaving a typed property
 * uninitialised and relying on callers to test it with isset(). Absence as
 * control flow is invisible to a reader and to static analysis; a route now
 * says whether it matched.
 */
final class Route
{
    public const string TYPE_STATIC = 'static';
    public const string TYPE_DYNAMIC = 'dynamic';
    public const string TYPE_VIEW = 'view';

    /**
     * @param array<string, string> $params
     */
    private function __construct(
        public readonly bool $matched,
        public readonly string $action = '',
        public readonly string $type = '',
        public readonly array $params = [],
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public static function to(string $action, string $type, array $params = []): self
    {
        return new self(true, $action, $type, $params);
    }

    public static function none(): self
    {
        return new self(false);
    }

    public function isView(): bool
    {
        return $this->type === self::TYPE_VIEW;
    }
}
