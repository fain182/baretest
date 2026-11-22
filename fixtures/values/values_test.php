<?php

use function Baretest\{test, equal};

test('null and bool', fn () => equal(null, false));
test('floats', fn () => equal(1.0, 1));
test('float precision', fn () => equal(0.1 + 0.2, 0.3));
test('escapes', fn () => equal("a\\b\"c\$d\r\n\x00", ''));
test('multibyte', fn () => equal("caffè", "caffe\u{300}"));
test('empty string', fn () => equal('', ' '));
