<?php

use function Baretest\{test, equal};

test('passes', fn () => equal(1, 1));

register_shutdown_function(fn () => exit(3));
