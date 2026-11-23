<?php

use Baretest\Core\Failure;
use Baretest\Core\FileResult;
use Baretest\Core\SuiteResult;
use Baretest\Core\TestFile;
use Baretest\Core\TestResult;

use function Baretest\{test, equal};

function passed(string $title = 'passes'): TestResult
{
    return new TestResult($title, 0.0, null);
}

function failed(string $title = 'fails'): TestResult
{
    return new TestResult($title, 0.0, new Failure('expected 1, got 2'));
}

function aFile(TestResult ...$tests): FileResult
{
    return new FileResult(new TestFile('a_test.php'), $tests, 0.0, '');
}

test('a test', function () {
    test('passes without a failure', fn () => equal(false, passed()->hasFailed()));
    test('fails with its own failure', fn () => equal(true, failed()->hasFailed()));
    test('fails when a subtest failed', fn () => equal(true, (new TestResult('parent', 0.0, null, true))->hasFailed()));
    test('is skipped with a reason', function () {
        $test = new TestResult('skipped', 0.0, null, false, 'needs mbstring');
        equal(true, $test->isSkipped());
        equal('needs mbstring', $test->getSkipReason());
    });
    test('that failed only through a subtest says so', function () {
        $cause = (new TestResult('parent', 0.0, null, true))->findCause();
        equal('a subtest failed', $cause->describe());
    });
    test('that passed has no cause', fn () => equal(null, passed()->findCause()));
});

test('a file', function () {
    test('counts the tests that failed, also through a subtest', function () {
        equal(2, aFile(passed(), failed(), new TestResult('parent', 0.0, null, true))->countFailed());
    });
    test('lists only the tests that failed themselves', function () {
        $file = aFile(failed('own'), new TestResult('parent', 0.0, null, true));
        equal(['own'], array_map(fn (TestResult $test) => $test->getTitle(), $file->listFailures()));
    });
    test('counts the skipped tests', fn () => equal(1, aFile(passed(), new TestResult('s', 0.0, null, false, 'why'))->countSkipped()));
    test('without tests says so', fn () => equal(true, aFile()->hasNoTests()));
});

test('a suite', function () {
    test('exits with 0 when every test passed', fn () => equal(0, (new SuiteResult([aFile(passed())], 0.0))->computeExitCode()));
    test('exits with 1 when a test failed', fn () => equal(1, (new SuiteResult([aFile(passed()), aFile(failed())], 0.0))->computeExitCode()));
    test('exits with 2 when a file ran no tests, even if another failed', function () {
        equal(2, (new SuiteResult([aFile(failed()), aFile()], 0.0))->computeExitCode());
    });
    test('counts across files', function () {
        $suite = new SuiteResult([aFile(passed(), failed()), aFile(), aFile(new TestResult('s', 0.0, null, false, 'why'))], 0.0);
        equal([3, 1, 1, 1, 1], [$suite->countTests(), $suite->countFailed(), $suite->countFailedFiles(), $suite->countEmptyFiles(), $suite->countSkipped()]);
    });
});

test('a file that crashed', function () {
    $printed = new FileResult(new TestFile('a_test.php'), [passed()], 0.0, implode("\n", range(1, 12)) . "\n");
    test('lists the crash after its tests', function () {
        $crashed = aFile(passed())->crash(3);
        equal(['passes', 'crashed'], array_map(fn (TestResult $test) => $test->getTitle(), $crashed->listTests()));
        equal('the process ended unexpectedly (exit code 3)', $crashed->listFailures()[0]->getFailure()->describe());
    });
    test('shows in the crash the last lines it printed, and no longer prints them', function () use ($printed) {
        $crashed = $printed->crash(139);
        equal("the process ended unexpectedly (exit code 139)\n3\n4\n5\n6\n7\n8\n9\n10\n11\n12", $crashed->listFailures()[0]->getFailure()->describe());
        equal('', $crashed->getOutput());
    });
    test('has failed', fn () => equal(true, aFile(passed())->crash(3)->hasFailures()));
});
