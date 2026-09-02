<?php

declare(strict_types=1);

namespace TetherPHP\framework\Traits;

trait Strings
{
    public function toPascalCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $string)));
    }

    public function toValidClassName(string $string): string
    {
        return $this->toPascalCase($string);
    }

    public function toKebabCase(string $string): string
    {
        $spaced = str_replace(['-', '_'], ' ', $string);

        // split on camel/pascal boundaries, leaving runs of capitals intact.
        // preg_replace returns null if the pattern fails, so keep the input.
        $split = preg_replace('/(?<!^)(?<![A-Z ])([A-Z])/', ' $1', $spaced) ?? $spaced;
        $collapsed = preg_replace('/\s+/', ' ', trim($split)) ?? $split;

        return strtolower(str_replace(' ', '-', $collapsed));
    }
}