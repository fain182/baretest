<?php

use function Baretest\{test, equal};

test('splits', function () {
    $cases = [
        'commas'      => [',', 'a,b', ['a', 'b']],
        'no match'    => [',', 'abc', ['abc']],
        'empty input' => [',', '',    ['']],
        'spaces'      => [' ', 'a b', ['a', 'b']],
    ];
    foreach ($cases as $name => [$separator, $input, $expected]) {
        test($name, fn () => equal($expected, explode($separator, $input)));
    }
});
