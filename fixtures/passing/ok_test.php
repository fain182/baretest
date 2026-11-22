<?php

use function Baretest\{test, equal, throws};

test('equal', function () {
    equal(['a' => 1, 'b' => [true, null]], ['a' => 1, 'b' => [true, null]]);
    equal("tab\t", "tab\t");
});

test('throws returns the exception', function () {
    $e = throws(InvalidArgumentException::class, function () { throw new InvalidArgumentException('bad'); });
    equal('bad', $e->getMessage());
    throws(Exception::class, function () { throw new RuntimeException(); });
});

test('expected warning', function () {
    $result = @hex2bin('abc');
    equal(false, $result);
    equal('hex2bin(): Hexadecimal input string must have an even length', error_get_last()['message']);
});

test('error_get_last is cleared before each test and subtest', function () {
    equal(null, error_get_last());
    @hex2bin('abc');
    test('subtest', fn () => equal(null, error_get_last()));
});

test('table', function () {
    foreach (['one' => [1, 1], 'two' => [2, 2]] as $name => [$expected, $actual]) {
        test($name, fn () => equal($expected, $actual));
    }
});
