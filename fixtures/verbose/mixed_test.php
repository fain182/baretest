<?php

use function Baretest\{test, equal};

test('first', fn () => equal(1, 1));
test('second', fn () => equal(1, 2));
test('third', function () {
    test('inner ok', fn () => equal(1, 1));
    test('inner broken', fn () => equal('a', 'b'));
});
