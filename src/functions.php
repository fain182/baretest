<?php

declare(strict_types=1);

namespace Baretest;

use Baretest\Worker\Session;

// Composer's autoloader loads this file in the worker, and a test file can require it again.
if (!function_exists('Baretest\test')) {
    function test(string $name, callable $fn): void
    {
        Session::runTest($name, $fn);
    }

    function equal($expected, $actual): void
    {
    }

    function throws(string $class, callable $fn): \Throwable
    {
        try {
            $fn();
        } catch (\Throwable $thrown) {
            return $thrown;
        }
    }
}
