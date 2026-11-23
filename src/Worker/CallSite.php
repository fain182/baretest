<?php

declare(strict_types=1);

namespace Baretest\Worker;

use Baretest\Core\Location;

/**
 * Where a test file called into baretest. PHP reports the last line of a call spread over
 * several lines up to 8.1 and its first from 8.2, so the whole call is found in the source.
 */
class CallSite
{
    private const REACH = 20;
    private const STRING_OR_COMMENT = '~\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|/\*.*?\*/|(?://|#)[^\n]*~s';

    /** @var array<string, list<string>> each file's lines, with strings and comments blanked out */
    private array $code = [];

    /** @param list<array{file?: string, line?: int, function: string}> $frames from debug_backtrace(), innermost first */
    public function find(array $frames): ?Location
    {
        $baretest = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        foreach ($frames as $frame) {
            if (isset($frame['file'], $frame['line']) && strpos($frame['file'], $baretest) !== 0) {
                return $this->locate($frame['file'], $frame['line'], $frame['function']);
            }
        }
        return null;
    }

    /** The call to $function that spans $line, the one that starts last if several do. */
    public function locate(string $file, int $line, string $function): Location
    {
        $first = max(1, $line - self::REACH);
        $window = implode("\n", array_slice($this->readCode($file), $first - 1, $line - $first + self::REACH + 1));
        $name = preg_quote(substr((string) strrchr('\\' . $function, '\\'), 1), '~');
        preg_match_all('~\b' . $name . '\s*\(~', $window, $calls, PREG_OFFSET_CAPTURE);
        foreach (array_reverse($calls[0]) as [$call, $offset]) {
            $start = $first + substr_count($window, "\n", 0, $offset);
            $close = $this->findClosingParenthesis($window, $offset + strlen($call));
            $end = $close === null ? null : $first + substr_count($window, "\n", 0, $close);
            if ($start <= $line && $end !== null && $line <= $end) {
                return new Location(self::makeRelative($file), $start, $end);
            }
        }
        return new Location(self::makeRelative($file), $line, $line);
    }

    private function findClosingParenthesis(string $code, int $offset): ?int
    {
        for ($depth = 1; $offset < strlen($code); $offset++) {
            $depth += ['(' => 1, ')' => -1][$code[$offset]] ?? 0;
            if ($depth === 0) {
                return $offset;
            }
        }
        return null;
    }

    /** @return list<string> */
    private function readCode(string $file): array
    {
        if (!isset($this->code[$file])) {
            $source = is_readable($file) ? (string) file_get_contents($file) : '';
            $blank = static fn (array $match): string => (string) preg_replace('~[^\n]~', ' ', $match[0]);
            $this->code[$file] = explode("\n", (string) preg_replace_callback(self::STRING_OR_COMMENT, $blank, $source));
        }
        return $this->code[$file];
    }

    private static function makeRelative(string $path): string
    {
        $prefix = getcwd() . DIRECTORY_SEPARATOR;
        return strncmp($path, $prefix, strlen($prefix)) === 0 ? substr($path, strlen($prefix)) : $path;
    }
}
