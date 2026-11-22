<?php

use function Baretest\{test, equal, throws, fail};

test('unexpected warning', function () {
    hex2bin('a');
});

test('unexpected deprecation', function () {
    trigger_error('old api', E_USER_DEPRECATED);
});

test('uncaught exception', function () {
    throw new DomainException('boom');
});

test('fail', fn () => fail("two\nlines"));

test('throws, nothing thrown', fn () => throws(InvalidArgumentException::class, fn () => 1));

test('throws, wrong class', fn () => throws(InvalidArgumentException::class, function () { throw new RuntimeException('x'); }));

test('failure swallowed by the test', function () {
    try {
        equal(1, 2);
    } catch (Throwable $e) {
    }
});

test('warning swallowed by the test', function () {
    try {
        trigger_error('lost', E_USER_WARNING);
    } catch (Throwable $e) {
    }
});

test('later tests still run', fn () => equal(1, 1));
