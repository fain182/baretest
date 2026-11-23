<?php

use Baretest\Execution\Worker\CallSite;

use function Baretest\{test, equal};

$code = <<<'PHP'
<?php
equal(1, 2);
equal(
    ['a' => 1],
    ['a' => 2],
);
equal(')', strtoupper( // )
    '\')(' /* ) */
));
test('arrow function', fn () => equal(
    1,
    2,
));
equal(1, 1); equal(
    2,
    3,
);
Baretest\fail('x');
PHP;

$file = tempnam(sys_get_temp_dir(), 'callsite');
file_put_contents($file, $code);

/** [first line, last line] of the call to $function that PHP reported at $line. */
function locate(string $file, int $line, string $function): array
{
    $location = (new CallSite())->locate($file, $line, $function);
    return [$location->getLine(), $location->getLastLine()];
}

test('finds the whole call from the line PHP reports, its first or its last', function () use ($file) {
    $cases = [
        'one line' => [[2], 'equal', [2, 2]],
        'arguments on their own lines' => [[3, 6], 'equal', [3, 6]],
        'parentheses in strings and comments' => [[7, 9], 'equal', [7, 9]],
        'inside an arrow function on the same line' => [[10, 13], 'equal', [10, 13]],
        'the call that starts last on a line with two' => [[14, 17], 'equal', [14, 17]],
        'a qualified name' => [[18], 'Baretest\fail', [18, 18]],
    ];
    foreach ($cases as $name => [$lines, $function, $expected]) {
        test($name, function () use ($file, $lines, $function, $expected) {
            foreach ($lines as $line) {
                equal($expected, locate($file, $line, $function));
            }
        });
    }
});

test('keeps the reported line when it cannot find the call', function () use ($file) {
    equal([5, 5], locate($file, 5, 'throws'));
    equal([1, 1], locate('/no/such/file.php', 1, 'equal'));
});

test('takes the first frame outside baretest, relative to the working directory', function () {
    $file = getcwd() . '/fixtures/passing/ok_test.php';
    $frames = [
        ['file' => dirname(__DIR__, 3) . '/src/functions.php', 'line' => 3, 'function' => 'fail'],
        ['file' => $file, 'line' => 6, 'function' => 'equal'],
    ];
    $location = (new CallSite())->find($frames);
    equal(['fixtures/passing/ok_test.php', 6, 6], [$location->getFile(), $location->getLine(), $location->getLastLine()]);
});

test('finds nothing when every frame is in baretest', function () {
    equal(null, (new CallSite())->find([['file' => dirname(__DIR__, 3) . '/src/functions.php', 'line' => 3, 'function' => 'x']]));
});

unlink($file);
