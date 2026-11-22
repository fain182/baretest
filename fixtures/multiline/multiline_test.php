<?php

use function Baretest\{test, equal, throws};

test('arguments on their own lines', function () {
    equal(
        ['a' => 1],
        ['a' => 2],
    );
});

test('parentheses in strings and comments', function () {
    equal(')', strtoupper( // )
        '\')(' /* ) */
    ));
});

test('arrow function', fn () => equal(
    1,
    2,
));

test('nothing thrown', function () {
    throws(
        LogicException::class,
        fn () => null,
    );
});

test('two calls on the first line', function () {
    equal(1, 1); equal(
        2,
        3,
    );
});
