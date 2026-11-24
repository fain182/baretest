<?php

use function Baretest\{test, equal};

test('null and bool', fn () => equal(null, false));
test('floats', fn () => equal(1.0, 1));
test('float precision', fn () => equal(0.1 + 0.2, 0.3));
test('escapes', fn () => equal("a\\b\"c\$d\r\n\x00", ''));
test('multibyte', fn () => equal("caffè", "caffe\u{300}"));
test('empty string', fn () => equal('', ' '));
test('each element that differs', function () {
    equal(
        ['return' => false, 'matches' => null, 'message' => 'preg_match(): Compilation failed: missing closing parenthesis at offset 3'],
        ['return' => null, 'matches' => null, 'message' => 'preg_match(): Compilation failed: missing ) at offset 3'],
    );
});
test('nested elements', fn () => equal(['a' => [1, [2, 3]], 'é' => 'x'], ['a' => [1, [2, 4]], 'é' => 'y']));
test('at most five elements', fn () => equal(range(1, 8), range(11, 18)));
test('keys that differ', fn () => equal(['a' => 1, 'b' => 2], ['a' => 1, 'c' => 3]));
