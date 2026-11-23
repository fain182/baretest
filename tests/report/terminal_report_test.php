<?php

use Baretest\Report\TerminalReport;
use Baretest\Core\Comparison;
use Baretest\Core\Failure;
use Baretest\Core\FileResult;
use Baretest\Core\Location;
use Baretest\Core\SuiteResult;
use Baretest\Core\TestFile;
use Baretest\Core\TestResult;

use function Baretest\{test, equal};

/** What the terminal prints for these files, one after the other, and then the summary. */
function printed(FileResult ...$files): string
{
    $stream = fopen('php://memory', 'w+');
    $terminal = new TerminalReport($stream, false);
    foreach ($files as $file) {
        $terminal->addFile($file);
    }
    $terminal->finish(new SuiteResult($files, 0.0));
    rewind($stream);
    return (string) stream_get_contents($stream);
}

function headerOf(FileResult $file): string
{
    return explode("\n", printed($file))[0];
}

function fileOf(array $tests, string $output = ''): FileResult
{
    return new FileResult(new TestFile('a_test.php'), $tests, 0.0, $output);
}

/** What the terminal prints under the header of a file with this one failed test. */
function detailOf(TestResult $test): string
{
    return implode("\n", array_slice(explode("\n", printed(fileOf([$test]))), 2, -2));
}

function ok(string $title = 'ok'): TestResult
{
    return new TestResult($title, 0.0, null);
}

$source = tempnam(sys_get_temp_dir(), 'source');
file_put_contents($source, "<?php\ntest('x', function () {\n    equal(\n        1,\n    );\n});\n" . str_repeat("\n", 3) . "    fail('ten');\n");

test('the header of a file', function () {
    test('counts its tests', fn () => equal('✓ a_test.php  2 tests  0.00s', headerOf(fileOf([ok(), ok()]))));
    test('counts the failed ones', fn () => equal('✗ a_test.php  1 of 2 failed  0.00s', headerOf(fileOf([ok(), new TestResult('x', 0.0, new Failure('no'))]))));
    test('says when there were none', fn () => equal('✗ a_test.php  no tests  0.00s', headerOf(fileOf([]))));
    test('counts the skipped ones', fn () => equal('✓ a_test.php  2 tests, 1 skipped  0.00s', headerOf(fileOf([ok(), new TestResult('s', 0.0, null, false, 'why')]))));
});

test('lists the skipped tests under their file, with the reason', function () {
    $file = fileOf([new TestResult('parent › child', 0.0, null, false, 'needs mbstring')]);
    equal("✓ a_test.php  1 test, 1 skipped  0.00s\n  - parent › child  needs mbstring  (skipped)\n", explode('✓ 1 test', printed($file))[0]);
});

test('shows a failure with its whole call, without the indentation the lines share', function () use ($source) {
    $failure = new Failure('expected LogicException to be thrown, nothing was thrown', null, new Location($source, 3, 5));
    equal(
        "  ✗ x  ($source:3)\n       3 │ equal(\n       4 │     1,\n       5 │ );\n\n       expected LogicException to be thrown, nothing was thrown\n",
        detailOf(new TestResult('x', 0.0, $failure)),
    );
});

test('aligns the line numbers of a call', function () use ($source) {
    $failure = new Failure('a', null, new Location($source, 9, 10));
    equal("  ✗ x  ($source:9)\n        9 │\n       10 │ fail('ten');\n\n       a\n", detailOf(new TestResult('x', 0.0, $failure)));
});

test('shows where a failure happened even when the file cannot be read', function () {
    $failure = new Failure("'break' not in the 'loop' or 'switch' context", 'Fatal error', new Location("a_test.php(6) : eval()'d code", 1, 1));
    equal(
        "  ✗ fatal error  (a_test.php(6) : eval()'d code:1)\n\n       Fatal error: 'break' not in the 'loop' or 'switch' context\n",
        detailOf(new TestResult('fatal error', 0.0, $failure)),
    );
});

test('shows a message line by line, and an empty one as an empty line', function () {
    equal("  ✗ a\n\n       two\n       lines\n", detailOf(new TestResult('a', 0.0, new Failure("two\nlines"))));
    equal("  ✗ b\n\n       \n", detailOf(new TestResult('b', 0.0, new Failure(''))));
});

test('shows the two values of equal() in a column, with the caret under the first difference', function () {
    $comparison = new Comparison('"caffè"', "\"caffe\u{300}\"", 'string', 'string', 5);
    equal(
        "  ✗ a\n\n       expected  \"caffè\"    (string)\n            got  \"caffe\u{300}\"    (string)\n                      ^\n",
        detailOf(new TestResult('a', 0.0, new Failure($comparison->describe(), null, null, $comparison))),
    );
});

test('shows what the file printed', function () {
    $printed = printed(fileOf([ok()], "at the top\nno newline"));
    equal("✓ a_test.php  1 test  0.00s\n  output\n       │ at the top\n       │ no newline\n\n✓ 1 test in 1 file  0.00s\n", $printed);
});

test('sums up the run', function () {
    $failed = fileOf([ok(), new TestResult('x', 0.0, new Failure('no'))]);
    $skipped = fileOf([new TestResult('s', 0.0, null, false, 'why')]);
    $last = fn (FileResult ...$files) => array_slice(explode("\n", printed(...$files)), -2, 1)[0];
    test('when every test passed', fn () => equal('✓ 2 tests in 2 files, 1 skipped  0.00s', $last(fileOf([ok()]), $skipped)));
    test('when files ran no tests', fn () => equal('✗ 1 of 2 files did not run any test  0.00s', $last(fileOf([ok()]), fileOf([]))));
    test('when tests failed', fn () => equal('✗ 1 of 3 tests failed in 1 of 3 files, 1 did not run any test, 1 skipped  0.00s', $last($failed, fileOf([]), $skipped)));
});

test('paints in colours when asked', function () {
    $stream = fopen('php://memory', 'w+');
    (new TerminalReport($stream, true))->addFile(fileOf([ok()]));
    rewind($stream);
    equal("\e[32m✓\e[0m a_test.php  1 test  \e[2m0.00s\e[0m\n", stream_get_contents($stream));
});

unlink($source);
