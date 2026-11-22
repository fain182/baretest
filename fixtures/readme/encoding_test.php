<?php

use function Baretest\{test, equal, skip};

test('one', fn () => equal(1, 1));
test('two', fn () => equal('é', 'é'));
test('lowercases accented letters', fn () => skip('needs mbstring'));
