<?php

use function Baretest\test;

test('fatal', function () {
    eval('break;');
});
