<?php

use function Baretest\{test, equal};

test('one', fn () => equal(1, 1));
test('two', fn () => equal('é', 'é'));
