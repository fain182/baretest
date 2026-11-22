<?php

declare(strict_types=1);

namespace Baretest\Output;

/** Lays out text in terminal columns. */
final class Columns
{
    private const VALUE_WIDTH = 48;
    private const CONTEXT_WIDTH = 16;
    private const ZERO_WIDTH = '~[\p{Mn}\p{Me}\x{200B}-\x{200F}\x{FE00}-\x{FE0F}]~u';
    private const WIDE = '~[\x{1100}-\x{115F}\x{2E80}-\x{303E}\x{3041}-\x{33FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{A000}-\x{A4CF}\x{AC00}-\x{D7A3}\x{F900}-\x{FAFF}\x{FE30}-\x{FE4F}\x{FF00}-\x{FF60}\x{FFE0}-\x{FFE6}\x{1F300}-\x{1F64F}\x{1F900}-\x{1F9FF}\x{20000}-\x{3FFFD}]~u';

    /** Wide East Asian characters and emoji take two columns, combining marks none. */
    public static function measure(string $text): int
    {
        return preg_match_all('~[^\x80-\xBF]~', $text) - (int) preg_match_all(self::ZERO_WIDTH, $text) + (int) preg_match_all(self::WIDE, $text);
    }

    /** Cuts long values to a window around the caret, the same window for both. */
    public static function cut(string $expected, string $got, ?int $caret): array
    {
        $a = self::splitCharacters($expected);
        $b = self::splitCharacters($got);
        if (max(self::measure($expected), self::measure($got)) <= self::VALUE_WIDTH) {
            return [$expected, $got, $caret];
        }
        $index = 0;
        for ($column = 0; $index < count($b) && $column < ($caret ?? 0); $index++) {
            $column += self::measure($b[$index]);
        }
        $start = $index;
        for ($context = 0; $start > 0 && $context + self::measure($b[$start - 1]) <= self::CONTEXT_WIDTH; $start--) {
            $context += self::measure($b[$start - 1]);
        }
        $window = static function (array $characters) use ($start): string {
            $rest = array_slice($characters, $start);
            $room = self::VALUE_WIDTH - ($start > 0 ? 1 : 0);
            if (self::measure(implode('', $rest)) <= $room) {
                return ($start > 0 ? '…' : '') . implode('', $rest);
            }
            $shown = '';
            foreach ($rest as $character) {
                $room -= self::measure($character);
                if ($room < 1) {
                    break;
                }
                $shown .= $character;
            }
            return ($start > 0 ? '…' : '') . $shown . '…';
        };
        $before = ($start > 0 ? '…' : '') . implode('', array_slice($b, $start, $index - $start));
        return [$window($a), $window($b), $caret === null ? null : self::measure($before)];
    }

    private static function splitCharacters(string $text): array
    {
        return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($text);
    }
}
