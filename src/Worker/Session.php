<?php

declare(strict_types=1);

namespace Baretest\Worker;

use Baretest\Output\Literal;
use Baretest\Result\TestResult;
use Baretest\Result\Records;

/**
 * The state of one test file, in the process that runs it. Every finished test is
 * appended to the records file at once, so that a crash loses only the test it hit.
 */
class Session
{
    private const FATAL = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE | E_USER_ERROR | E_RECOVERABLE_ERROR;

    /** The innermost running test; the tests around it are its parents. */
    private static ?Test $current = null;
    private static ?Records $records = null;
    private static bool $finished = false;
    private static bool $fileFailed = false;
    private static int $started = 0;

    public static function runFile(string $file, string $recordsPath): void
    {
        self::$records = new Records($recordsPath);
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
            // already recorded where it was raised
        } catch (\Throwable $e) {
            self::failFile('uncaught exception', self::describe($e));
        }
        self::$finished = true;
        self::$records->write(['t' => 'done']);
    }

    public static function runTest(string $name, callable $fn): void
    {
        if (self::$records === null) {
            throw new \LogicException('Test files are run with vendor/bin/baretest, not directly.');
        }
        error_clear_last();
        $test = new Test($name, self::$started++, self::$current);
        self::$current = $test;
        try {
            $fn();
        } catch (Failure $failure) {
            // already recorded where it was raised
        } catch (Skip $skip) {
            $test->skip($skip->getMessage());
        } catch (\Throwable $e) {
            $test->fail(self::describe($e));
        }
        self::$current = $test->getParent();
        if (self::$current !== null && $test->hasFailed()) {
            self::$current->markSubtestFailed();
        }
        self::$records->write($test->buildRecord());
    }

    /** @return never */
    public static function failValues($expected, $got)
    {
        $failure = [
            'expected' => Literal::render($expected),
            'got' => Literal::render($got),
            'expectedType' => Literal::describeType($expected),
            'gotType' => Literal::describeType($got),
            'caret' => Literal::findOffset($expected, $got),
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
        if (self::$current === null) {
            self::fail('skip() was called outside a test; to skip a whole file, put its tests inside one test');
        }
        throw new Skip($reason);
    }

    /** @return never */
    private static function raise(array $failure)
    {
        self::failHere($failure + CallSite::find(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));
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
        self::failHere(['label' => $label, 'message' => $message, 'file' => $file, 'line' => $line]);
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
        if (self::$current !== null) {
            $failure['message'] .= "\nwhile running: " . TestResult::formatTitle(self::$current->listPath());
        }
        self::failFile($fatal ? 'fatal error' : 'exit() called', $failure);
    }

    /** Fails the running test, or the file when no test is running. */
    private static function failHere(array $failure): void
    {
        if (self::$current === null) {
            self::failFile('outside a test', $failure);
        } else {
            self::$current->fail($failure);
        }
    }

    /** Fails the file outside any test; only the first such failure is kept, as it stopped the file. */
    private static function failFile(string $title, array $failure): void
    {
        if (!self::$fileFailed) {
            self::$fileFailed = true;
            self::$records->write(['t' => 'file', 'title' => $title, 'failure' => $failure]);
        }
    }

    private static function describe(\Throwable $e): array
    {
        return ['label' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
    }
}
