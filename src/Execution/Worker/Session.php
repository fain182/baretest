<?php

declare(strict_types=1);

namespace Baretest\Execution\Worker;

use Baretest\Core\Comparison;
use Baretest\Core\Failure;
use Baretest\Core\RunningTest;
use Baretest\Core\TestResult;
use Baretest\Execution\Records;

/**
 * The test file running in this process, and the test running in it. Any warning, exit() or
 * fatal error becomes the failure of that test, or of the file outside any test.
 */
class Session
{
    private const FATAL = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE | E_USER_ERROR | E_RECOVERABLE_ERROR;
    private const LABELS = [
        E_WARNING => 'Warning', E_USER_WARNING => 'Warning', E_CORE_WARNING => 'Warning', E_COMPILE_WARNING => 'Warning',
        E_NOTICE => 'Notice', E_USER_NOTICE => 'Notice', E_DEPRECATED => 'Deprecated', E_USER_DEPRECATED => 'Deprecated',
    ];

    private static ?Records $records = null;
    private static CallSite $callSite;
    private static ?RunningTest $current = null;
    private static int $started = 0;
    private static bool $finished = false;
    private static bool $fileFailed = false;

    public static function runFile(string $file, Records $records): void
    {
        self::$records = $records;
        self::$callSite = new CallSite();
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '0');
        set_error_handler(\Closure::fromCallable([self::class, 'onError']));
        register_shutdown_function(\Closure::fromCallable([self::class, 'onShutdown']));
        try {
            (static function (string $file): void {
                require $file;
            })($file);
        } catch (StopSignal $stop) {
        } catch (\Throwable $e) {
            self::failFile('uncaught exception', self::describe($e));
        }
        self::$finished = true;
        $records->close();
    }

    public static function runTest(string $name, callable $fn): void
    {
        if (self::$records === null) {
            throw new \LogicException('Test files are run with vendor/bin/baretest, not directly.');
        }
        error_clear_last();
        $order = self::$started++;
        $test = new RunningTest($name, self::$current);
        self::$current = $test;
        try {
            $fn();
        } catch (StopSignal $stop) {
        } catch (SkipSignal $skip) {
            $test->skip($skip->getMessage());
        } catch (\Throwable $e) {
            $test->fail(self::describe($e));
        }
        self::$current = $test->getParent();
        self::$records->add($order, $test->finish());
    }

    /** @return never */
    public static function failValues($expected, $got)
    {
        $comparisons = Comparison::listDifferences($expected, $got);
        self::raise(implode("\n", array_map(static fn (Comparison $comparison): string => $comparison->describe(), $comparisons)), $comparisons);
    }

    /** @return never */
    public static function fail(string $message)
    {
        self::raise($message);
    }

    /** @return never */
    public static function skip(string $reason)
    {
        if (self::$current === null) {
            self::raise('skip() was called outside a test; to skip a whole file, put its tests inside one test');
        }
        throw new SkipSignal($reason);
    }

    /** @return never */
    /** @param list<Comparison> $comparisons */
    private static function raise(string $message, array $comparisons = [])
    {
        self::failHere(new Failure($message, null, self::$callSite->find(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)), $comparisons));
        throw new StopSignal($message);
    }

    private static function onError(int $level, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $level)) {
            return false;
        }
        self::failHere(new Failure($message, self::LABELS[$level] ?? 'Error', self::$callSite->locateLine($file, $line)));
        throw new StopSignal($message);
    }

    private static function onShutdown(): void
    {
        if (self::$finished) {
            return;
        }
        $last = error_get_last();
        $fatal = $last !== null && ($last['type'] & self::FATAL);
        $message = $fatal ? $last['message'] : 'the file called exit()';
        if (self::$current !== null) {
            $message .= "\nwhile running: " . self::$current->formatTitle();
        }
        $failure = $fatal ? new Failure($message, 'Fatal error', self::$callSite->locateLine($last['file'], $last['line'])) : new Failure($message);
        self::failFile($fatal ? 'fatal error' : 'exit() called', $failure);
    }

    private static function failHere(Failure $failure): void
    {
        if (self::$current === null) {
            self::failFile('outside a test', $failure);
        } else {
            self::$current->fail($failure);
        }
    }

    /** Only the first failure outside any test is kept: it stopped the file. */
    private static function failFile(string $title, Failure $failure): void
    {
        if (!self::$fileFailed) {
            self::$fileFailed = true;
            self::$records->failFile(new TestResult($title, 0.0, $failure));
        }
    }

    private static function describe(\Throwable $e): Failure
    {
        return new Failure($e->getMessage(), get_class($e), self::$callSite->locateLine($e->getFile(), $e->getLine()));
    }
}
