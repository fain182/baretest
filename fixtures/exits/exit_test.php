<?php

use function Baretest\{test, equal};

test('before', fn () => equal(1, 1));

test('exits', function () {
    test('inner', function () {
        exit(0);
    });
});
