<?php

declare(strict_types=1);

namespace Baretest;

require_once __DIR__ . '/Failure.php';
require_once __DIR__ . '/Value.php';
require_once __DIR__ . '/Session.php';

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
    } catch (Failure $failure) {
        throw $failure;
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
