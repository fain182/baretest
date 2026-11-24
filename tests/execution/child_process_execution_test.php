<?php

use Baretest\Core\FileResult;
use Baretest\Core\TestFile;
use Baretest\Core\TestResult;
use Baretest\Execution\ChildProcessExecution;

use function Baretest\{test, equal};

/** Runs $code as a test file in a child process. */
function runFile(string $code): FileResult
{
    $file = tempnam(sys_get_temp_dir(), 'child') . '_test.php';
    file_put_contents($file, "<?php\nuse function Baretest\\{test, equal};\n" . $code);
    try {
        return (new ChildProcessExecution(dirname(__DIR__, 2) . '/vendor/autoload.php'))->run(new TestFile($file));
    } finally {
        unlink($file);
    }
}

function titles(FileResult $result): array
{
    return array_map(fn (TestResult $test) => $test->getTitle(), $result->listTests());
}

function lastCause(FileResult $result): string
{
    $tests = $result->listTests();
    return end($tests)->findCause()->describe();
}

test('lists the tests in the order they started', function () {
    $result = runFile("test('parent', fn () => test('child', fn () => equal(1, 1)));\ntest('next', fn () => equal(1, 1));");
    equal(['parent', 'parent › child', 'next'], titles($result));
});

test('keeps what the file printed', function () {
    equal("at the top\n", runFile('echo "at the top\n";')->getOutput());
});

test('fails the file that called exit(), after the tests that finished', function () {
    $result = runFile("test('before', fn () => equal(1, 1));\ntest('exits', fn () => exit(0));");
    equal(['before', 'exit() called'], titles($result));
    equal("the file called exit()\nwhile running: exits", lastCause($result));
});

test('fails a file whose process ended with an error after its tests', function () {
    $result = runFile("test('passes', fn () => equal(1, 1));\nregister_shutdown_function(fn () => exit(3));");
    equal(['passes', 'crashed'], titles($result));
    equal('the process ended unexpectedly (exit code 3)', lastCause($result));
});

test('shows the last lines a crashed file printed in its failure', function () {
    $result = runFile("echo implode(\"\\n\", range(1, 12));\nregister_shutdown_function(fn () => exit(3));");
    equal("the process ended unexpectedly (exit code 3)\n3\n4\n5\n6\n7\n8\n9\n10\n11\n12", lastCause($result));
    equal('', $result->getOutput());
});

test('reports every level of error, unless this process was given another', function () {
    $level = error_reporting();
    equal((string) E_ALL, runFile("echo error_reporting();")->getOutput());
    try {
        error_reporting(E_ALL & ~E_DEPRECATED);
        equal((string) (E_ALL & ~E_DEPRECATED), runFile("echo error_reporting();")->getOutput());
    } finally {
        error_reporting($level);
    }
});

test('runs the file with the settings of this process', function () {
    ini_set('precision', '7');
    equal('7', runFile("echo ini_get('precision');")->getOutput());
});
