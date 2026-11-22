<?php

declare(strict_types=1);

namespace Baretest\Worker;

/** Finds the whole call in a test file that failed, even when it spans several lines. */
class CallSite
{
    private const CALL_LINES = 10;
    private const STRING_OR_COMMENT = '~\'(?:[^\'\\\\]++|\\\\.)*+\'|"(?:[^"\\\\]++|\\\\.)*+"|/\*.*?\*/|(?://|#)[^\n]*~s';

    /** @var array<string, list<string>> the lines of each test file, with strings and comments blanked out */
    private static array $code = [];

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

    /** The first and last line of the call to $function that spans $line, the one that starts last if several do. */
    private static function findCall(string $file, int $line, string $function): array
    {
        $first = max(1, $line - self::CALL_LINES);
        $code = implode("\n", array_slice(self::readCode($file), $first - 1, $line - $first + self::CALL_LINES + 1));
        $name = substr((string) strrchr('\\' . $function, '\\'), 1);
        preg_match_all('~\b' . preg_quote($name, '~') . '\s*\(~', $code, $matches, PREG_OFFSET_CAPTURE);
        foreach (array_reverse($matches[0]) as [$call, $offset]) {
            $close = self::findClosingParenthesis($code, $offset + strlen($call));
            $start = $first + substr_count(substr($code, 0, $offset), "\n");
            $end = $close === null ? null : $first + substr_count(substr($code, 0, $close), "\n");
            if ($start <= $line && $end !== null && $end >= $line) {
                return ['line' => $start, 'lastLine' => $end];
            }
        }
        return ['line' => $line];
    }

    private static function findClosingParenthesis(string $code, int $offset): ?int
    {
        for ($depth = 1; $offset < strlen($code); $offset++) {
            if ($code[$offset] === '(') {
                $depth++;
            } elseif ($code[$offset] === ')' && --$depth === 0) {
                return $offset;
            }
        }
        return null;
    }

    /** @return list<string> */
    private static function readCode(string $file): array
    {
        if (!isset(self::$code[$file])) {
            $text = is_readable($file) ? (string) file_get_contents($file) : '';
            $blank = static fn (array $match): string => (string) preg_replace('~[^\n]~', ' ', $match[0]);
            self::$code[$file] = explode("\n", (string) preg_replace_callback(self::STRING_OR_COMMENT, $blank, $text));
        }
        return self::$code[$file];
    }
}
