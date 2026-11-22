<?php

use function Baretest\{test, equal, throws, fail};

test('unexpected warning', function () {
    $x = [];
    echo $x['missing'];
});

test('unexpected deprecation', function () {
    trigger_error('old api', E_USER_DEPRECATED);
});

test('uncaught exception', function () {
    throw new DomainException('boom');
});

test('fail', fn () => fail("two\nlines"));

test('throws, nothing thrown', fn () => throws(InvalidArgumentException::class, fn () => 1));

test('throws, wrong class', fn () => throws(InvalidArgumentException::class, fn () => throw new RuntimeException('x')));

test('failure swallowed by the test', function () {
    try {
        equal(1, 2);
    } catch (Throwable) {
    }
});

test('warning swallowed by the test', function () {
    try {
        trigger_error('lost', E_USER_WARNING);
    } catch (Throwable) {
    }
});

test('later tests still run', fn () => equal(1, 1));
