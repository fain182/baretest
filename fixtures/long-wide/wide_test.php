<?php

use function Baretest\{test, equal};

test('wide', fn () => equal(str_repeat('日本', 20) . 'x' . str_repeat('語', 30), str_repeat('日本', 20) . 'y' . str_repeat('語', 30)));
