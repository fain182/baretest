<?php

// What vendor/autoload.php does for the "files" entry in composer.json.
require __DIR__ . '/../../src/functions.php';

use function Baretest\{test, equal};

test('runs', fn () => equal(1, 1));
