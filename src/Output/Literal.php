<?php

declare(strict_types=1);

namespace Baretest\Output;

/** Shows PHP values byte by byte, and finds where two of them first differ. */
final class Literal
{
    private const UTF8 = '~[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}|.~s';

    public static function render($value): string
    {
        switch (true) {
            case $value === null: return 'null';
            case is_bool($value): return $value ? 'true' : 'false';
            case is_int($value): return (string) $value;
            case is_float($value): return is_nan($value) ? 'NAN' : (is_infinite($value) ? ($value > 0 ? 'INF' : '-INF') : var_export($value, true));
            case is_string($value): return '"' . implode('', self::tokenize($value)) . '"';
            case is_array($value): return self::renderArray($value);
            case $value instanceof \UnitEnum: return get_class($value) . '::' . $value->name;
            case is_object($value): return get_class($value) . '#' . spl_object_id($value);
            default: return self::describeType($value);
        }
    }

    /** What get_debug_type(), missing before PHP 8.0, returns. */
    public static function describeType($value): string
    {
        if (is_object($value)) {
            return explode("\0", get_class($value))[0];
        }
        if (is_resource($value)) {
            return 'resource (' . get_resource_type($value) . ')';
        }
        $names = ['NULL' => 'null', 'boolean' => 'bool', 'integer' => 'int', 'double' => 'float'];
        return $names[gettype($value)] ?? gettype($value);
    }

    /** Column in render($got) of the first element or byte that differs from $expected. */
    public static function findOffset($expected, $got): ?int
    {
        if (is_string($expected) && is_string($got)) {
            $a = self::tokenize($expected);
            $b = self::tokenize($got);
            $column = 1;
            foreach ($b as $i => $token) {
                if (($a[$i] ?? null) !== $token) {
                    break;
                }
                $column += Columns::measure($token);
            }
            return $column;
        }
        if (is_array($expected) && is_array($got)) {
            return self::findArrayOffset($expected, $got);
        }
        return null;
    }

    /** One token per character: valid UTF-8 as it is, anything else escaped. */
    private static function tokenize(string $text): array
    {
        preg_match_all(self::UTF8, $text, $matches);
        return array_map(static function (string $char): string {
            $byte = ord($char[0]);
            switch (true) {
                case $byte >= 0xC2 && strlen($char) > 1: return $char;
                case $char === "\n": return '\n';
                case $char === "\t": return '\t';
                case $char === "\r": return '\r';
                case $char === '\\' || $char === '"' || $char === '$': return '\\' . $char;
                case $byte >= 0x20 && $byte < 0x7F: return $char;
                default: return sprintf('\x%02X', $byte);
            }
        }, $matches[0]);
    }

    private static function renderArray(array $array): string
    {
        $list = array_values($array) === $array;
        $parts = [];
        foreach ($array as $key => $value) {
            $parts[] = ($list ? '' : self::renderKey($key)) . self::render($value);
        }
        return '[' . implode(', ', $parts) . ']';
    }

    /** @param int|string $key */
    private static function renderKey($key): string
    {
        return self::render($key) . ' => ';
    }

    private static function findArrayOffset(array $expected, array $got): int
    {
        $list = array_values($got) === $got;
        $expectedKeys = array_keys($expected);
        $column = 1;
        foreach (array_keys($got) as $i => $key) {
            if (($expectedKeys[$i] ?? null) !== $key) {
                return $column;
            }
            $prefix = $list ? 0 : Columns::measure(self::renderKey($key));
            if ($expected[$key] !== $got[$key]) {
                return $column + $prefix + (self::findOffset($expected[$key], $got[$key]) ?? 0);
            }
            $column += $prefix + Columns::measure(self::render($got[$key])) + 2;
        }
        return $got === [] ? $column : $column - 2;
    }
}
