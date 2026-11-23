<?php

declare(strict_types=1);

namespace Baretest\Worker;

/** PHP values as the failure of equal() shows them, byte by byte. */
class Literal
{
    /** One well-formed UTF-8 character, or else a single byte. */
    private const CHARACTER = '~[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]'
        . '|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}|.~s';
    private const ESCAPES = ["\n" => '\n', "\r" => '\r', "\t" => '\t', '\\' => '\\\\', '"' => '\"', '$' => '\$'];

    public static function render($value): string
    {
        if (is_string($value)) {
            return '"' . implode('', self::tokenize($value)) . '"';
        }
        if (is_array($value)) {
            $list = array_values($value) === $value;
            $entries = [];
            foreach ($value as $key => $item) {
                $entries[] = ($list ? '' : self::renderKey($key)) . self::render($item);
            }
            return '[' . implode(', ', $entries) . ']';
        }
        if ($value instanceof \UnitEnum) {
            return get_class($value) . '::' . $value->name;
        }
        if (is_object($value)) {
            return self::describeType($value) . '#' . spl_object_id($value);
        }
        if (is_float($value) || is_int($value)) {
            return var_export($value, true);
        }
        return is_bool($value) ? ($value ? 'true' : 'false') : self::describeType($value);
    }

    /** What get_debug_type() returns, which PHP 7.4 lacks. */
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

    /** The byte of render($got) where it first differs from $expected, for two strings or two arrays. */
    public static function findOffset($expected, $got): ?int
    {
        if (is_string($expected) && is_string($got)) {
            $theirs = self::tokenize($expected);
            $offset = 1;
            foreach (self::tokenize($got) as $i => $token) {
                if (($theirs[$i] ?? null) !== $token) {
                    break;
                }
                $offset += strlen($token);
            }
            return $offset;
        }
        return is_array($expected) && is_array($got) ? self::findArrayOffset($expected, $got) : null;
    }

    private static function findArrayOffset(array $expected, array $got): int
    {
        $list = array_values($got) === $got;
        $keys = array_keys($expected);
        $offset = 1;
        foreach (array_keys($got) as $i => $key) {
            if (($keys[$i] ?? null) !== $key) {
                return $offset;
            }
            $offset += $list ? 0 : strlen(self::renderKey($key));
            if ($expected[$key] !== $got[$key]) {
                return $offset + (self::findOffset($expected[$key], $got[$key]) ?? 0);
            }
            $offset += strlen(self::render($got[$key])) + 2;
        }
        return $got === [] ? $offset : $offset - 2;
    }

    /** @param int|string $key */
    private static function renderKey($key): string
    {
        return self::render($key) . ' => ';
    }

    /** @return list<string> each character as it is shown */
    private static function tokenize(string $text): array
    {
        preg_match_all(self::CHARACTER, $text, $matches);
        return array_map(static function (string $character): string {
            if (isset(self::ESCAPES[$character])) {
                return self::ESCAPES[$character];
            }
            $printable = strlen($character) > 1 || (ord($character) >= 0x20 && ord($character) < 0x7F);
            return $printable ? $character : sprintf('\x%02X', ord($character));
        }, $matches[0]);
    }
}
