<?php

use Baretest\Worker\CallSite;
use function Baretest\{test, equal};

/** What CallSite::find returns for a call to equal() reported at $line of a file holding $code. */
function findEqual(string $code, int $line): array
{
    $file = tempnam(sys_get_temp_dir(), 'callsite');
    try {
        file_put_contents($file, $code);
        $found = CallSite::find([['file' => $file, 'line' => $line, 'function' => 'Baretest\equal']]);
        unset($found['file']);
        return $found;
    } finally {
        unlink($file);
    }
}

test('the lines of the call', function () {
    $multiline = "equal(\n    1,\n    2,\n);";
    $cases = [
        'one line'                       => ["equal(1, 2);", 1, [1, 1]],
        'reported on its first line'     => [$multiline, 1, [1, 4]],
        'reported on its last line'      => [$multiline, 4, [1, 4]],
        'parentheses in strings'         => ["equal(')', \"(\",\n    '\\')(',\n);", 3, [1, 3]],
        'parentheses in comments'        => ["equal(1, // )\n    2 /* ( */\n);", 3, [1, 3]],
        'two calls on its first line'    => ["equal(1, 1); equal(\n    2,\n    3,\n);", 1, [1, 4]],
        'its name in a comment above'    => ["// equal(\nequal(\n    1,\n    2,\n);", 5, [2, 5]],
        'its name in a string above'     => ["\$a = 'equal(';\nequal(\n    1,\n    2,\n);", 5, [2, 5]],
        'nested in another call'         => ["test('x', fn () => equal(\n    1,\n    2,\n));", 4, [1, 4]],
    ];
    foreach ($cases as $name => [$code, $line, [$first, $last]]) {
        test($name, fn () => equal(['line' => $first, 'lastLine' => $last], findEqual($code, $line)));
    }
});

test('only the reported line when the call is not found', function () {
    test('called through an alias', fn () => equal(['line' => 2], findEqual("\$f = 'x';\neq(1, 2);", 2)));
    test('the file cannot be read', fn () => equal(
        ['file' => '/nonexistent', 'line' => 3],
        CallSite::find([['file' => '/nonexistent', 'line' => 3, 'function' => 'Baretest\equal']]),
    ));
});

test('the first frame outside baretest', function () {
    $session = dirname(__DIR__) . '/src/Worker/Session.php';
    $test = __FILE__;
    test('skips the frames of baretest', fn () => equal(
        $test,
        CallSite::find([['file' => $session, 'line' => 1, 'function' => 'raise'], ['file' => $test, 'line' => 1, 'function' => 'Baretest\equal']])['file'],
    ));
    test('skips frames without a file', fn () => equal(
        $test,
        CallSite::find([['function' => '{closure}'], ['file' => $test, 'line' => 1, 'function' => 'Baretest\equal']])['file'],
    ));
    test('nothing when every frame is in baretest', fn () => equal(
        [],
        CallSite::find([['file' => $session, 'line' => 1, 'function' => 'raise']]),
    ));
});
