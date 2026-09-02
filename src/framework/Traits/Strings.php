<?php

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
        $string = str_replace(['-', '_'], ' ', $string);

        // split on camel/pascal boundaries, leaving runs of capitals intact
        $string = preg_replace('/(?<!^)(?<![A-Z ])([A-Z])/', ' $1', $string);

        return strtolower(str_replace(' ', '-', preg_replace('/\s+/', ' ', trim($string))));
    }
}