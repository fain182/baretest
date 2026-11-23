<?php

use Baretest\Core\FileResult;
use Baretest\Core\RunningTest;
use Baretest\Core\SlowTest;
use Baretest\Core\SuiteResult;
use Baretest\Core\TestFile;
use Baretest\Core\TestResult;

use function Baretest\{test, equal};

/** A file whose tests took these seconds, named t0, t1, ... */
function timed(string $path, float ...$seconds): FileResult
{
    $tests = [];
    foreach ($seconds as $i => $time) {
        $tests[] = new TestResult("t$i", $time, null);
    }
    return new FileResult(new TestFile($path), $tests, 0.0, '');
}

/** @return list<string> "file › test" for each slow test of these files */
function slowOf(FileResult ...$files): array
{
    return array_map(
        fn (SlowTest $slow) => $slow->getFile()->getPath() . ' › ' . $slow->getTest()->getTitle(),
        (new SuiteResult($files, 0.0))->listSlowTests(),
    );
}

test('lists a test that took half a second and ten times the median', function () {
    equal(['a › t3'], slowOf(timed('a', 0.01, 0.01, 0.01, 0.5)));
});

test('does not list a test that is quick, however many times the median', function () {
    equal([], slowOf(timed('a', 0.001, 0.001, 0.001, 0.4)));
});

test('does not list a test that is not ten times the median, however long', function () {
    equal([], slowOf(timed('a', 1.0, 1.0, 1.0, 9.0)));
});

test('takes the median of an even number of tests between the two middle ones', function () {
    equal(['a › t3'], slowOf(timed('a', 0.25, 0.25, 0.75, 5.0)));
});

test('compares the tests of every file, and says where each slow one is', function () {
    equal(['b › t0', 'a › t1'], slowOf(timed('a', 0.01, 0.6), timed('b', 1.0, 0.01, 0.01)));
});

test('lists the five slowest, slowest first', function () {
    $file = timed('a', ...array_merge(array_fill(0, 20, 0.01), [0.6, 0.7, 0.8, 0.9, 1.0, 1.1]));
    equal(['a › t25', 'a › t24', 'a › t23', 'a › t22', 'a › t21'], slowOf($file));
});

test('counts only the tests without subtests, whose time is their own', function () {
    $parent = new RunningTest('parent', null);
    $child = new RunningTest('child', $parent);
    $child->finish();
    $tests = [new TestResult('parent', 2.0, null, false, null, $parent->finish()->hasSubtests()), new TestResult('parent › child', 1.9, null), new TestResult('quick', 0.01, null), new TestResult('quicker', 0.01, null)];
    equal(['a › parent › child'], slowOf(new FileResult(new TestFile('a'), $tests, 0.0, '')));
});

test('leaves out what did not run as a test, like a crash', function () {
    equal([], slowOf(timed('a', 0.0, 0.0, 0.6)));
});

test('lists nothing for a run without tests', fn () => equal([], slowOf()));
