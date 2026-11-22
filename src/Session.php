<?php

declare(strict_types=1);

namespace Baretest;

/**
 * The state of one test file, in the process that runs it. Every finished test is
 * appended to the records file at once, so that a crash loses only the test it hit.
 */
final class Session
{
    private const CALL_LINES = 10;
    private const FATAL = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE | E_USER_ERROR | E_RECOVERABLE_ERROR;

    /** @var list<array{name: string, seq: int, failure: ?array, subtestFailed: bool, skipped: ?string}> */
    private static array $active = [];
    /** @var resource|null */
    private static $records = null;
    private static bool $finished = false;
    private static bool $fileFailed = false;
    private static int $started = 0;

    public static function runFile(string $file, string $recordsPath): void
    {
        self::$records = fopen($recordsPath, 'ab');
        error_reporting(E_ALL);
        // Errors become failures; PHP's own copy would show up again as the file's output.
        ini_set('display_errors', '0');
        ini_set('log_errors', '0');
        set_error_handler(self::onError(...));
        register_shutdown_function(self::onShutdown(...));

        try {
            (static function (string $file): void {
                require $file;
            })($file);
        } catch (Failure) {
            // already registered where it was raised
        } catch (\Throwable $e) {
            self::register(self::describe($e), 'uncaught exception');
        }
        self::$finished = true;
        self::write(['t' => 'done']);
    }

    public static function run(string $name, callable $fn): void
    {
        if (self::$records === null) {
            throw new \LogicException('Test files are run with vendor/bin/baretest, not directly.');
        }
        error_clear_last();
        $start = microtime(true);
        self::$active[] = ['name' => $name, 'seq' => self::$started++, 'failure' => null, 'subtestFailed' => false, 'skipped' => null];
        try {
            $fn();
        } catch (Failure) {
            // already registered where it was raised
        } catch (Skip $skip) {
            self::$active[array_key_last(self::$active)]['skipped'] = $skip->getMessage();
        } catch (\Throwable $e) {
            self::register(self::describe($e));
        }
        $path = array_column(self::$active, 'name');
        $test = array_pop(self::$active);
        if (self::$active !== [] && ($test['failure'] !== null || $test['subtestFailed'])) {
            self::$active[array_key_last(self::$active)]['subtestFailed'] = true;
        }
        $skipped = $test['failure'] === null && !$test['subtestFailed'] ? $test['skipped'] : null;
        self::write(['t' => 'test', 'seq' => $test['seq'], 'path' => $path, 'time' => microtime(true) - $start, 'failure' => $test['failure'], 'subtestFailed' => $test['subtestFailed'], 'skipped' => $skipped]);
    }

    public static function failValues(mixed $expected, mixed $got): never
    {
        $failure = [
            'expected' => Value::render($expected),
            'got' => Value::render($got),
            'expectedType' => get_debug_type($expected),
            'gotType' => get_debug_type($got),
            'caret' => Value::findOffset($expected, $got),
        ];
        $failure['message'] = sprintf('expected %s (%s), got %s (%s)', $failure['expected'], $failure['expectedType'], $failure['got'], $failure['gotType']);
        self::raise($failure);
    }

    public static function fail(string $message): never
    {
        self::raise(['message' => $message]);
    }

    public static function skip(string $reason): never
    {
        if (self::$active === []) {
            self::fail('skip() was called outside a test; to skip a whole file, put its tests inside one test');
        }
        throw new Skip($reason);
    }

    private static function raise(array $failure): never
    {
        self::register($failure + self::findCallSite());
        throw new Failure($failure['message']);
    }

    private static function onError(int $level, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $level)) {
            return false;
        }
        $label = match ($level) {
            E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => 'Warning',
            E_NOTICE, E_USER_NOTICE => 'Notice',
            E_DEPRECATED, E_USER_DEPRECATED => 'Deprecated',
            default => 'Error',
        };
        self::register(['label' => $label, 'message' => $message, 'file' => $file, 'line' => $line]);
        throw new Failure($message);
    }

    private static function onShutdown(): void
    {
        if (self::$finished) {
            return;
        }
        $last = error_get_last();
        $fatal = $last !== null && ($last['type'] & self::FATAL);
        $failure = $fatal
            ? ['label' => 'Fatal error', 'message' => $last['message'], 'file' => $last['file'], 'line' => $last['line']]
            : ['message' => 'the file called exit()'];
        if (self::$active !== []) {
            $failure['message'] .= "\nwhile running: " . implode(' › ', array_column(self::$active, 'name'));
        }
        self::$active = [];
        self::register($failure, $fatal ? 'fatal error' : 'exit() called');
    }

    private static function register(array $failure, string $title = 'outside a test'): void
    {
        if (self::$active === []) {
            if (!self::$fileFailed) {
                self::$fileFailed = true;
                self::write(['t' => 'file', 'title' => $title, 'failure' => $failure]);
            }
            return;
        }
        self::$active[array_key_last(self::$active)]['failure'] ??= $failure;
    }

    private static function describe(\Throwable $e): array
    {
        return ['label' => $e::class, 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
    }

    /** The lines of the test code that called into baretest. */
    private static function findCallSite(): array
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (isset($frame['file']) && !str_starts_with($frame['file'], __DIR__ . DIRECTORY_SEPARATOR)) {
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

    private static function write(array $record): void
    {
        fwrite(self::$records, json_encode($record, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
    }
}
