<?php

use Baretest\Core\Comparison;
use Baretest\Core\Failure;
use Baretest\Core\Location;

use function Baretest\{test, equal};

test('a failure', function () {
    test('is its message', fn () => equal("two\nlines", (new Failure("two\nlines"))->describe()));
    test('puts its label first', fn () => equal('Warning: lost', (new Failure('lost', 'Warning'))->describe()));
    test('is only its label without a message', fn () => equal('Exception', (new Failure('', 'Exception'))->describe()));
    test('may say where it happened', function () {
        $location = new Location('tests/a_test.php', 6, 9);
        equal($location, (new Failure('x', null, $location))->getLocation());
    });
});

test('a location is written file:line, on its first line', function () {
    equal('tests/a_test.php:6', (new Location('tests/a_test.php', 6, 9))->format());
});

test('a comparison describes both values with their types', function () {
    equal('expected "50%\n" (string), got "x" (string)', (new Comparison('"50%\n"', '"x"', 'string', 'string', 1))->describe());
    equal('["a"][0]: expected 1 (int), got 2 (int)', (new Comparison('1', '2', 'int', 'int', null, '["a"][0]'))->describe());
});

test('a comparison of two values', function () {
    $paths = fn ($expected, $got) => array_map(fn (Comparison $comparison) => $comparison->getPath(), Comparison::listDifferences($expected, $got));
    test('is one for values that are not both arrays', fn () => equal([''], $paths([1], '1')));
    test('is one for arrays with other keys', fn () => equal([''], $paths(['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1])));
    test('is one for each element that differs in arrays with the same keys', fn () => equal(['["a"][1]', '[0]'], $paths(['a' => [1, 2, 3], 0 => 'x'], ['a' => [1, 5, 3], 0 => 'y'])));
    test('shows only the element that differs', function () {
        $comparison = Comparison::listDifferences(['a' => 'xy'], ['a' => 'xz'])[0];
        equal(['"xy"', '"xz"', 'string', 'string', 2], [$comparison->getExpected(), $comparison->getGot(), $comparison->getExpectedType(), $comparison->getGotType(), $comparison->getCaret()]);
    });
});
