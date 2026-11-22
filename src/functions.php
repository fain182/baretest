<?php

declare(strict_types=1);

namespace Baretest;

require_once __DIR__ . '/Failure.php';
require_once __DIR__ . '/Skip.php';
require_once __DIR__ . '/Value.php';
require_once __DIR__ . '/Session.php';

// The worker loads this file, and so does Composer's autoload.php when a test file requires it.
if (!function_exists('Baretest\test')) {
    function test(string $name, callable $fn): void
    {
        Session::run($name, $fn);
    }

    function equal(mixed $expected, mixed $actual): void
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
            Session::fail(sprintf('expected %s to be thrown, got %s: %s', $class, $thrown::class, $thrown->getMessage()));
        }
        Session::fail(sprintf('expected %s to be thrown, nothing was thrown', $class));
    }

    function fail(string $message): never
    {
        Session::fail($message);
    }

    function skip(string $reason): never
    {
        Session::skip($reason);
    }
}
