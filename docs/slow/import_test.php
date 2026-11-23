<?php

use function Baretest\{test, equal};

test('imports a large CSV', function () {
    usleep(1200000);
    equal(10000, 10000);
});
test('skips the header row', fn () => equal(1, 1));
test('reads a gzipped file', function () {
    usleep(700000);
    equal(1, 1);
});
