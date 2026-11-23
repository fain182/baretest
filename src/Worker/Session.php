<?php

declare(strict_types=1);

namespace Baretest\Worker;

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
        $fn();
        self::$current = $test->getParent();
        self::$records->add($order, $test->finish());
    }
}
