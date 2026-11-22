<?php

declare(strict_types=1);

namespace Baretest;

/** Shows PHP values byte by byte, and finds where two of them first differ. */
final class Value
{
    private const UTF8 = '~[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}|.~s';

    private const ZERO_WIDTH = '~[\p{Mn}\p{Me}\x{200B}-\x{200F}\x{FE00}-\x{FE0F}]~u';
    private const WIDE = '~[\x{1100}-\x{115F}\x{2E80}-\x{303E}\x{3041}-\x{33FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{A000}-\x{A4CF}\x{AC00}-\x{D7A3}\x{F900}-\x{FAFF}\x{FE30}-\x{FE4F}\x{FF00}-\x{FF60}\x{FFE0}-\x{FFE6}\x{1F300}-\x{1F64F}\x{1F900}-\x{1F9FF}\x{20000}-\x{3FFFD}]~u';

    public static function render(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            is_float($value) => is_nan($value) ? 'NAN' : (is_infinite($value) ? ($value > 0 ? 'INF' : '-INF') : var_export($value, true)),
            is_string($value) => '"' . implode('', self::tokenize($value)) . '"',
            is_array($value) => self::renderArray($value),
            $value instanceof \UnitEnum => $value::class . '::' . $value->name,
            is_object($value) => $value::class . '#' . spl_object_id($value),
            default => get_debug_type($value),
        };
    }

    /** Column in render($got) of the first element or byte that differs from $expected. */
    public static function findOffset(mixed $expected, mixed $got): ?int
    {
        if (is_string($expected) && is_string($got)) {
            $a = self::tokenize($expected);
            $b = self::tokenize($got);
            $column = 1;
            foreach ($b as $i => $token) {
                if (($a[$i] ?? null) !== $token) {
                    break;
                }
                $column += self::measureWidth($token);
            }
            return $column;
        }
        if (is_array($expected) && is_array($got)) {
            return self::findArrayOffset($expected, $got);
        }
        return null;
    }

    /** Terminal columns: wide East Asian characters and emoji take two, combining marks none. */
    public static function measureWidth(string $text): int
    {
        return preg_match_all('~[^\x80-\xBF]~', $text) - (int) preg_match_all(self::ZERO_WIDTH, $text) + (int) preg_match_all(self::WIDE, $text);
    }

    /** One token per character: valid UTF-8 as it is, anything else escaped. */
    private static function tokenize(string $text): array
    {
        preg_match_all(self::UTF8, $text, $matches);
        return array_map(static function (string $char): string {
            $byte = ord($char[0]);
            return match (true) {
                $byte >= 0xC2 && strlen($char) > 1 => $char,
                $char === "\n" => '\n',
                $char === "\t" => '\t',
                $char === "\r" => '\r',
                $char === '\\', $char === '"', $char === '$' => '\\' . $char,
                $byte >= 0x20 && $byte < 0x7F => $char,
                default => sprintf('\x%02X', $byte),
            };
        }, $matches[0]);
    }

    private static function renderArray(array $array): string
    {
        $list = array_is_list($array);
        $parts = [];
        foreach ($array as $key => $value) {
            $parts[] = ($list ? '' : self::renderKey($key)) . self::render($value);
        }
        return '[' . implode(', ', $parts) . ']';
    }

    private static function renderKey(int|string $key): string
    {
        return self::render($key) . ' => ';
    }

    private static function findArrayOffset(array $expected, array $got): int
    {
        $list = array_is_list($got);
        $expectedKeys = array_keys($expected);
        $column = 1;
        foreach (array_keys($got) as $i => $key) {
            if (($expectedKeys[$i] ?? null) !== $key) {
                return $column;
            }
            $prefix = $list ? 0 : self::measureWidth(self::renderKey($key));
            if ($expected[$key] !== $got[$key]) {
                return $column + $prefix + (self::findOffset($expected[$key], $got[$key]) ?? 0);
            }
            $column += $prefix + self::measureWidth(self::render($got[$key])) + 2;
        }
        return $got === [] ? $column : $column - 2;
    }
}
