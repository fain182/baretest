<?php

declare(strict_types=1);

namespace Baretest;

use Baretest\Worker\Failure;
use Baretest\Worker\Session;
use Baretest\Worker\Skip;

// Composer's autoload.php loads this file in the worker, and a test file can load it again.
if (!function_exists('Baretest\test')) {
    function test(string $name, callable $fn): void
    {
        Session::run($name, $fn);
    }

    function equal($expected, $actual): void
    {
        if ($expected !== $actual) {
            Session::failValues($expected, $actual);
        }
    }

    function throws(string $class, callable $fn): \Throwable
    {
        try {
            $fn();
        } catch (Failure | Skip $stop) {
            throw $stop;
        } catch (\Throwable $thrown) {
            if ($thrown instanceof $class) {
                return $thrown;
            }
            Session::fail(sprintf('expected %s to be thrown, got %s: %s', $class, get_class($thrown), $thrown->getMessage()));
        }
        Session::fail(sprintf('expected %s to be thrown, nothing was thrown', $class));
    }

    /** @return never */
    function fail(string $message)
    {
        Session::fail($message);
    }

    /** @return never */
    function skip(string $reason)
    {
        Session::skip($reason);
    }
}
