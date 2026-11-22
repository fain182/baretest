<?php

use Baretest\Value;
use function Baretest\{test, equal};

test('render', function () {
    $cases = [
        'null'       => [null, 'null'],
        'bool'       => [false, 'false'],
        'int'        => [-3, '-3'],
        'float'      => [1.0, '1.0'],
        'nan'        => [NAN, 'NAN'],
        'list'       => [[1, 'a'], '[1, "a"]'],
        'keyed'      => [[1 => 'a'], '[1 => "a"]'],
        'empty'      => [[], '[]'],
        'invalid'    => ["b\xE0\xA0c", '"b\xE0\xA0c"'],
        'multibyte'  => ["caffè", '"caffè"'],
        'overlong'   => ["\xC0\x80", '"\xC0\x80"'],
        'control'    => ["\x00\x7F", '"\x00\x7F"'],
    ];
    foreach ($cases as $name => [$value, $expected]) {
        test($name, fn () => equal($expected, Value::render($value)));
    }
});

test('offset', function () {
    $cases = [
        'strings'        => ['abc', 'abd', 3],
        'after escapes'  => ["\n\xE0x", "\n\xE0y", 7],
        'got is longer'  => ['ab', 'abc', 3],
        'got is shorter' => ['abc', 'ab', 3],
        'list element'   => [[1, 2], [1, 3], 4],
        'extra element'  => [[1], [1, 2], 4],
        'nested'         => [[[1, 2]], [[1, 3]], 5],
        'keyed'          => [['a' => 1, 'b' => 2], ['a' => 1, 'b' => 3], 18],
        'scalars'        => [1, 2, null],
        'types'          => [3, '3', null],
        'wide'           => ['日本語x', '日本語y', 7],
        'combining'      => ["e\u{301}x", "e\u{301}y", 2],
    ];
    foreach ($cases as $name => [$expected, $got, $offset]) {
        test($name, fn () => equal($offset, Value::offset($expected, $got)));
    }
});
