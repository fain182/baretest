<?php

use function Baretest\{test, equal, skip};

test('runs', fn () => equal(1, 1));
skip('whole file');
