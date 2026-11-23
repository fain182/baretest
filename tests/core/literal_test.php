<?php

use Baretest\Core\Literal;

use function Baretest\{test, equal};

if (PHP_VERSION_ID >= 80100) {
    eval('enum Suit { case Hearts; }');
}

test('renders', function () {
    $cases = [
        'null' => [null, 'null'],
        'booleans' => [[true, false], '[true, false]'],
        'an int' => [-3, '-3'],
        'a float that looks whole' => [1.0, '1.0'],
        'a float as precise as it is' => [0.1 + 0.2, '0.30000000000000004'],
        'escapes' => ["a\\b\"c\$d\r\n\t\x00", '"a\\\\b\"c\$d\r\n\t\x00"'],
        'valid UTF-8 as it is' => ['caffè 日本', '"caffè 日本"'],
        'invalid UTF-8 as bytes' => ["b\xE0\xA0c\xFF", '"b\xE0\xA0c\xFF"'],
        'a list' => [[1, 'a'], '[1, "a"]'],
        'a map' => [['a' => [1], 2 => null], '["a" => [1], 2 => null]'],
    ];
    foreach ($cases as $name => [$value, $rendered]) {
        test($name, fn () => equal($rendered, Literal::render($value)));
    }
    test('an object by class and id', function () {
        $object = new ArrayObject();
        equal('ArrayObject#' . spl_object_id($object), Literal::render($object));
    });
    test('an enum case', function () {
        if (PHP_VERSION_ID < 80100) {
            Baretest\skip('enums need PHP 8.1');
        }
        equal('Suit::Hearts', Literal::render(constant('Suit::Hearts')));
    });
});

test('describes the type', function () {
    equal(['null', 'bool', 'int', 'float', 'string', 'array', 'ArrayObject'], array_map([Literal::class, 'describeType'], [null, true, 1, 1.5, '', [], new ArrayObject()]));
    equal('class@anonymous', Literal::describeType(new class () {}));
    equal('resource (stream)', Literal::describeType(STDIN));
});

test('finds the byte of the rendered got where the values first differ', function () {
    $cases = [
        'strings' => ['abc', 'abd', 3],
        'an empty string' => ['', ' ', 1],
        'a combining mark against a precomposed letter' => ['caffè', "caffe\u{300}", 5],
        'an escaped byte' => ["b\xE0c", "b\xE0\xA0c", 6],
        'a value in a list' => [[1, 2], [1, 3], 4],
        'a value in a map' => [['a' => 1], ['a' => 2], 8],
        'a key' => [['a' => 1], ['b' => 1], 1],
        'a nested value' => [['a' => ['b' => [1, 2]]], ['a' => ['b' => [1, 3]]], 20],
        'the end of a shorter got' => [[1, 2, 3], [1, 2], 5],
        'an empty got' => [[1], [], 1],
        'an element only got has' => [['a', 'b'], ['a', 'b', ''], 11],
    ];
    foreach ($cases as $name => [$expected, $got, $offset]) {
        test($name, fn () => equal($offset, Literal::findOffset($expected, $got)));
    }
    test('nowhere for other values', fn () => equal([null, null], [Literal::findOffset(3, '3'), Literal::findOffset(1, 2)]));
});
