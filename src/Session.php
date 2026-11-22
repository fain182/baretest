<?php

declare(strict_types=1);

namespace Baretest;

/**
 * The state of one test file, in the process that runs it. Every finished test is
 * appended to the records file at once, so that a crash loses only the test it hit.
 */
final class Session
{
    private const FATAL = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE | E_USER_ERROR | E_RECOVERABLE_ERROR;

    /** @var list<array{name: string, failure: ?array}> */
    private static array $active = [];
    /** @var resource|null */
    private static $records = null;
    private static bool $finished = false;
    private static bool $fileFailed = false;

    public static function runFile(string $file, string $recordsPath): void
    {
        self::$records = fopen($recordsPath, 'ab');
        error_reporting(E_ALL);
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
        self::$active[] = ['name' => $name, 'failure' => null];
        try {
            $fn();
        } catch (Failure) {
            // already registered where it was raised
        } catch (\Throwable $e) {
            self::register(self::describe($e));
        }
        $path = array_column(self::$active, 'name');
        $test = array_pop(self::$active);
        self::write(['t' => 'test', 'path' => $path, 'time' => microtime(true) - $start, 'failure' => $test['failure']]);
    }

    public static function failValues(mixed $expected, mixed $got): never
    {
        $failure = [
            'expected' => Value::render($expected),
            'got' => Value::render($got),
            'expectedType' => get_debug_type($expected),
            'gotType' => get_debug_type($got),
            'caret' => Value::offset($expected, $got),
        ];
        $failure['message'] = sprintf('expected %s (%s), got %s (%s)', $failure['expected'], $failure['expectedType'], $failure['got'], $failure['gotType']);
        self::raise($failure);
    }

    public static function fail(string $message): never
    {
        self::raise(['message' => $message]);
    }

    private static function raise(array $failure): never
    {
        self::register($failure + self::callSite());
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

    /** The line of the test code that called into baretest. */
    private static function callSite(): array
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (isset($frame['file']) && !str_starts_with($frame['file'], __DIR__ . DIRECTORY_SEPARATOR)) {
                return ['file' => $frame['file'], 'line' => $frame['line']];
            }
        }
        return [];
    }

    private static function write(array $record): void
    {
        fwrite(self::$records, json_encode($record, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
    }
}
