<?php

declare(strict_types=1);

namespace Baretest\Worker;

use Baretest\Core\Failure;
use Baretest\Process\Records;

/** The test file running in this process, and the test running in it. */
class Session
{
    private static Records $records;
    private static ?RunningTest $current = null;
    private static int $started = 0;

    public static function runFile(string $file, Records $records): void
    {
        self::$records = $records;
        error_reporting(E_ALL);
        (static function (string $file): void {
            require $file;
        })($file);
        $records->close();
    }

    public static function runTest(string $name, callable $fn): void
    {
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
            $test->fail(new Failure($e->getMessage(), get_class($e)));
        }
        self::$current = $test->getParent();
        $result = $test->finish();
        if (self::$current !== null && $result->hasFailed()) {
            self::$current->markSubtestFailed();
        }
        self::$records->add($order, $result);
    }

    /** @return never */
    public static function skip(string $reason)
    {
        throw new SkipSignal($reason);
    }

    /** @return never */
    public static function fail(string $message)
    {
        self::$current->fail(new Failure($message));
        throw new StopSignal($message);
    }
}
