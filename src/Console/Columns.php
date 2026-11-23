<?php

declare(strict_types=1);

namespace Baretest\Console;

/** Lays out text in the columns of a terminal. */
class Columns
{
    private const WIDTH = 48;
    private const CONTEXT = 16;
    private const ZERO_WIDTH = '~[\p{Mn}\p{Me}\x{200B}-\x{200F}\x{FE00}-\x{FE0F}]~u';
    private const WIDE = '~[\x{1100}-\x{115F}\x{2E80}-\x{303E}\x{3041}-\x{33FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{A000}-\x{A4CF}'
        . '\x{AC00}-\x{D7A3}\x{F900}-\x{FAFF}\x{FE30}-\x{FE4F}\x{FF00}-\x{FF60}\x{FFE0}-\x{FFE6}\x{1F300}-\x{1F64F}\x{1F900}-\x{1F9FF}\x{20000}-\x{3FFFD}]~u';

    /** Wide East Asian characters and emoji take two columns, combining marks none. */
    public static function measure(string $text): int
    {
        return (int) preg_match_all('~.~su', $text) - (int) preg_match_all(self::ZERO_WIDTH, $text) + (int) preg_match_all(self::WIDE, $text);
    }

    /**
     * Cuts values wider than the window to the same window around the caret.
     *
     * @param ?int $caret a byte of $got
     * @return array{string, string, ?int} the two values, and the column of the caret in them
     */
    public static function cut(string $expected, string $got, ?int $caret): array
    {
        $column = $caret === null ? null : self::measure(substr($got, 0, $caret));
        if (max(self::measure($expected), self::measure($got)) <= self::WIDTH) {
            return [$expected, $got, $column];
        }
        $before = self::split(substr($got, 0, $caret ?? 0));
        $context = 0;
        for ($start = count($before); $start > 0 && $context + self::measure($before[$start - 1]) <= self::CONTEXT; $start--) {
            $context += self::measure($before[$start - 1]);
        }
        $lead = $start > 0 ? '…' : '';
        return [self::window($expected, $start, $lead), self::window($got, $start, $lead), $column === null ? null : self::measure($lead) + $context];
    }

    private static function window(string $text, int $start, string $lead): string
    {
        $rest = array_slice(self::split($text), $start);
        $room = self::WIDTH - self::measure($lead);
        if (self::measure(implode('', $rest)) <= $room) {
            return $lead . implode('', $rest);
        }
        $shown = '';
        foreach ($rest as $character) {
            if (self::measure($shown . $character) >= $room) {
                break;
            }
            $shown .= $character;
        }
        return $lead . $shown . '…';
    }

    /** @return list<string> */
    private static function split(string $text): array
    {
        return preg_split('~~u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
