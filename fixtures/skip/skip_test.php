<?php

use function Baretest\{test, equal, skip, throws};

test('skipped', fn () => skip('needs mbstring'));
test('after a check', function () {
    equal(1, 1);
    skip('not on this PHP');
    equal(1, 2);
});
test('parent', function () {
    test('skipped child', fn () => skip('child reason'));
    test('passing child', fn () => equal(1, 1));
});
test('failed then skipped', function () {
    test('child', fn () => equal(1, 2));
    skip('too late');
});
test('inside throws', fn () => throws(Exception::class, fn () => skip('from throws')));
