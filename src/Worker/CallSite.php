<?php

declare(strict_types=1);

namespace Baretest\Worker;

/** Finds the whole call in a test file that failed, even when it spans several lines. */
final class CallSite
{
    private const CALL_LINES = 10;

    /** The lines of the test code that called into baretest. */
    public static function find(): array
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (isset($frame['file']) && strpos($frame['file'], dirname(__DIR__) . DIRECTORY_SEPARATOR) !== 0) {
                return ['file' => $frame['file']] + self::findCall($frame['file'], $frame['line'], $frame['function']);
            }
        }
        return [];
    }

    /** The first and last line of the call to $function that contains $line, which can be either of them. */
    private static function findCall(string $file, int $line, string $function): array
    {
        $lines = is_readable($file) ? (file($file, FILE_IGNORE_NEW_LINES) ?: []) : [];
        $pattern = '~\b' . preg_quote(substr((string) strrchr('\\' . $function, '\\'), 1), '~') . '\s*\(~';
        for ($start = $line - 1; $start >= max(0, $line - self::CALL_LINES); $start--) {
            if (preg_match($pattern, $lines[$start] ?? '', $match, PREG_OFFSET_CAPTURE)) {
                $end = self::findCallEnd($lines, $start, $match[0][1] + strlen($match[0][0]));
                if ($end !== null && $end + 1 >= $line) {
                    return ['line' => $start + 1, 'lastLine' => $end + 1];
                }
            }
        }
        return ['line' => $line];
    }

    /** The line of the parenthesis that closes the one before $column, skipping strings and comments. */
    private static function findCallEnd(array $lines, int $start, int $column): ?int
    {
        $depth = 1;
        $quote = null;
        $comment = false;
        for ($i = $start; $i < min(count($lines), $start + self::CALL_LINES); $i++, $column = 0) {
            $text = $lines[$i];
            for ($c = $column; $c < strlen($text); $c++) {
                $pair = substr($text, $c, 2);
                if ($comment) {
                    [$comment, $c] = $pair === '*/' ? [false, $c + 1] : [true, $c];
                } elseif ($quote !== null) {
                    [$quote, $c] = $text[$c] === '\\' ? [$quote, $c + 1] : [$text[$c] === $quote ? null : $quote, $c];
                } elseif ($text[$c] === '"' || $text[$c] === "'") {
                    $quote = $text[$c];
                } elseif ($text[$c] === '#' || $pair === '//') {
                    break;
                } elseif ($pair === '/*') {
                    [$comment, $c] = [true, $c + 1];
                } elseif ($text[$c] === '(') {
                    $depth++;
                } elseif ($text[$c] === ')' && --$depth === 0) {
                    return $i;
                }
            }
        }
        return null;
    }
}
