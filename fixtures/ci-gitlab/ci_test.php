<?php

use function Baretest\{test, equal};

test('passes', fn () => equal(1, 1));
test('fails, with: odd,chars', fn () => equal("50%\n", 'x'));
test('<xml> & "quotes"', fn () => throw new Exception('a < b'));
test('empty message', fn () => Baretest\fail(''));
test('parent', fn () => test('child', fn () => equal(1, 2)));
