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
});
