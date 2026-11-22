<?php

use function Baretest\{test, equal};

test('before', fn () => equal(1, 1));

equal(1, 2);

test('never reached', fn () => equal(1, 1));
