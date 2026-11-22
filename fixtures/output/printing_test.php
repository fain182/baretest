<?php

use function Baretest\{test, equal};

echo "at the top\n";

test('dumps', function () {
    var_dump([1, 'a']);
    equal(1, 1);
});

test('fails', function () {
    echo 'no newline';
    equal(1, 2);
});
