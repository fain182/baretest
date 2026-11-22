<?php

declare(strict_types=1);

namespace Baretest\Worker;

use Baretest\Output\Value;

/**
 * The state of one test file, in the process that runs it. Every finished test is
 * appended to the records file at once, so that a crash loses only the test it hit.
 */
final class Session
{
    private const FATAL = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE | E_USER_ERROR | E_RECOVERABLE_ERROR;

    /** @var list<Test> the running test and the tests around it, outermost first */
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
        set_error_handler(\Closure::fromCallable([self::class, 'onError']));
        register_shutdown_function(\Closure::fromCallable([self::class, 'onShutdown']));

        try {
            (static function (string $file): void {
                require $file;
            })($file);
        } catch (Failure $failure) {
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
        $test = new Test($name, self::$started++);
        self::$active[] = $test;
        try {
            $fn();
        } catch (Failure $failure) {
            // already registered where it was raised
        } catch (Skip $skip) {
            $test->skip($skip->getMessage());
        } catch (\Throwable $e) {
            self::register(self::describe($e));
        }
        $path = array_column(self::$active, 'name');
        array_pop(self::$active);
        if (self::$active !== [] && $test->hasFailed()) {
            self::$active[array_key_last(self::$active)]->markSubtestFailed();
        }
        self::write($test->toRecord($path));
    }

    /** @return never */
    public static function failValues($expected, $got)
    {
        $failure = [
            'expected' => Value::render($expected),
            'got' => Value::render($got),
            'expectedType' => Value::describeType($expected),
            'gotType' => Value::describeType($got),
            'caret' => Value::findOffset($expected, $got),
        ];
        $failure['message'] = sprintf('expected %s (%s), got %s (%s)', $failure['expected'], $failure['expectedType'], $failure['got'], $failure['gotType']);
        self::raise($failure);
    }

    /** @return never */
    public static function fail(string $message)
    {
        self::raise(['message' => $message]);
    }

    /** @return never */
    public static function skip(string $reason)
    {
        if (self::$active === []) {
            self::fail('skip() was called outside a test; to skip a whole file, put its tests inside one test');
        }
        throw new Skip($reason);
    }

    /** @return never */
    private static function raise(array $failure)
    {
        self::register($failure + CallSite::find());
        throw new Failure($failure['message']);
    }

    private static function onError(int $level, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $level)) {
            return false;
        }
        $label = [
            E_WARNING => 'Warning', E_USER_WARNING => 'Warning', E_CORE_WARNING => 'Warning', E_COMPILE_WARNING => 'Warning',
            E_NOTICE => 'Notice', E_USER_NOTICE => 'Notice',
            E_DEPRECATED => 'Deprecated', E_USER_DEPRECATED => 'Deprecated',
        ][$level] ?? 'Error';
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
        self::$active[array_key_last(self::$active)]->fail($failure);
    }

    private static function describe(\Throwable $e): array
    {
        return ['label' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
    }

    private static function write(array $record): void
    {
        fwrite(self::$records, base64_encode(serialize($record)) . "\n");
    }
}
