<?php

use Baretest\Report\Columns;

use function Baretest\{test, equal};

test('measures the columns a text takes', function () {
    $cases = [
        'nothing' => ['', 0],
        'ASCII' => ['abc', 3],
        'a precomposed letter' => ['caffè', 5],
        'a combining mark takes none' => ["caffe\u{300}", 5],
        'wide characters take two' => ['日本', 4],
        'an emoji takes two' => ["\u{1F600}", 2],
    ];
    foreach ($cases as $name => [$text, $columns]) {
        test($name, fn () => equal($columns, Columns::measure($text)));
    }
});

test('keeps short values, with the caret at its column', function () {
    equal(['"caffè"', "\"caffe\u{300}\"", 5], Columns::cut('"caffè"', "\"caffe\u{300}\"", 5));
    equal(['"日本"', '"日本x"', 5], Columns::cut('"日本"', '"日本x"', 7));
    equal(['1', '2', null], Columns::cut('1', '2', null));
});

test('cuts long values to a window around the caret, the same for both', function () {
    $expected = '"' . str_repeat('abcdefghij', 20) . 'X' . str_repeat('k', 100) . '"';
    $got = '"' . str_repeat('abcdefghij', 20) . 'Y' . str_repeat('k', 100) . '"';
    equal([
        '…efghijabcdefghijX' . str_repeat('k', 29) . '…',
        '…efghijabcdefghijY' . str_repeat('k', 29) . '…',
        17,
    ], Columns::cut($expected, $got, 201));
});

test('counts wide characters as two columns when it cuts', function () {
    $expected = '"' . str_repeat('日本', 20) . 'x' . str_repeat('語', 30) . '"';
    $got = '"' . str_repeat('日本', 20) . 'y' . str_repeat('語', 30) . '"';
    equal([
        '…日本日本日本日本x' . str_repeat('語', 14) . '…',
        '…日本日本日本日本y' . str_repeat('語', 14) . '…',
        17,
    ], Columns::cut($expected, $got, 121));
});

test('cuts from the start when there is no caret', function () {
    $long = str_repeat('a', 60);
    equal([str_repeat('a', 47) . '…', 'b', null], Columns::cut($long, 'b', null));
});
