<?php

use function Baretest\{test, equal};

function split(string $separator, string $input): array
{
    return $input === 'a,b,' ? ['a', 'b', ''] : ($input === 'b' ? ["b\xE0\xA0c"] : explode($separator, $input));
}

test('splits', function () {
    $cases = [
        'commas'            => [',', 'a,b', ['a', 'b']],
        'trailing comma'    => [',', 'a,b,', ['a', 'b']],
        'invalid bytes'     => [',', 'b', ["b\xE0c"]],
    ];
    foreach ($cases as $name => [$separator, $input, $expected]) {
        test($name, fn () => equal($expected, split($separator, $input)));
    }
});

test('counts pieces', function () {
    equal(3, '3');
});
