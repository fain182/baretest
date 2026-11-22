<?php

use function Baretest\{test, equal};

test('long string', fn () => equal(str_repeat('abcdefghij', 20) . 'X' . str_repeat('k', 100), str_repeat('abcdefghij', 20) . 'Y' . str_repeat('k', 100)));
test('long array', fn () => equal(range(1, 100), [...range(1, 60), 0, ...range(62, 100)]));
test('nested', fn () => equal(['a' => ['b' => [1, 2]], 'c' => 1], ['a' => ['b' => [1, 3]], 'c' => 1]));
test('got is shorter', fn () => equal([1, 2, 3], [1, 2]));
test('keys differ', fn () => equal(['a' => 1], ['b' => 1]));
