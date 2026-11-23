<?php

use Baretest\Report\GithubReport;
use Baretest\Report\GitlabReport;
use Baretest\Core\Failure;
use Baretest\Core\FileResult;
use Baretest\Core\Location;
use Baretest\Core\SuiteResult;
use Baretest\Core\TestFile;
use Baretest\Core\TestResult;

use function Baretest\{test, equal, throws};

function ciFile(): FileResult
{
    return new FileResult(new TestFile('tests/a_test.php'), [
        new TestResult('passes', 0.0, null),
        new TestResult('fails, with: odd,chars', 0.0, new Failure('expected "50%\n" (string), got "x" (string)', null, new Location('tests/a_test.php', 6, 6))),
        new TestResult('<xml> & "quotes"', 0.0, new Failure("a < b\nsecond line", 'Exception', new Location('tests/helper.php', 7, 7))),
        new TestResult('crashed', 0.0, new Failure('the process ended unexpectedly (exit code 3)')),
        new TestResult('parent', 0.0, null, true),
        new TestResult('skipped', 0.0, null, false, 'a < b'),
    ], 0.25, '');
}

test('GitHub gets an annotation for each test that failed itself', function () {
    $stream = fopen('php://memory', 'w+');
    $github = new GithubReport($stream);
    $github->addFile(ciFile());
    $github->finish(new SuiteResult([ciFile()], 0.0));
    rewind($stream);
    equal(
        "::error file=tests/a_test.php,line=6,title=fails%2C with%3A odd%2Cchars::expected \"50%25\\n\" (string), got \"x\" (string)\n"
        . "::error file=tests/helper.php,line=7,title=<xml> & \"quotes\"::Exception: a < b%0Asecond line\n"
        . "::error file=tests/a_test.php,title=crashed::the process ended unexpectedly (exit code 3)\n",
        stream_get_contents($stream),
    );
});

test('GitLab gets a JUnit report of the whole run', function () {
    $path = tempnam(sys_get_temp_dir(), 'junit');
    (new GitlabReport($path))->finish(new SuiteResult([ciFile()], 0.0));
    $xml = file_get_contents($path);
    unlink($path);
    equal(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="tests/a_test.php" tests="6" failures="4" skipped="1" time="0.250">
    <testcase classname="tests/a_test.php" name="passes" time="0.000"/>
    <testcase classname="tests/a_test.php" name="fails, with: odd,chars" time="0.000">
      <failure message="expected &quot;50%\n&quot; (string), got &quot;x&quot; (string)">tests/a_test.php:6
expected &quot;50%\n&quot; (string), got &quot;x&quot; (string)</failure>
    </testcase>
    <testcase classname="tests/a_test.php" name="&lt;xml&gt; &amp; &quot;quotes&quot;" time="0.000">
      <failure message="Exception: a &lt; b">tests/helper.php:7
Exception: a &lt; b
second line</failure>
    </testcase>
    <testcase classname="tests/a_test.php" name="crashed" time="0.000">
      <failure message="the process ended unexpectedly (exit code 3)">tests/a_test.php
the process ended unexpectedly (exit code 3)</failure>
    </testcase>
    <testcase classname="tests/a_test.php" name="parent" time="0.000">
      <failure message="a subtest failed">tests/a_test.php
a subtest failed</failure>
    </testcase>
    <testcase classname="tests/a_test.php" name="skipped" time="0.000">
      <skipped message="a &lt; b"/>
    </testcase>
  </testsuite>
</testsuites>

XML, $xml);
});

test('GitLab drops the control characters XML cannot hold', function () {
    $path = tempnam(sys_get_temp_dir(), 'junit');
    $file = new FileResult(new TestFile('a_test.php'), [new TestResult("bell\x07", 0.0, null)], 0.0, '');
    (new GitlabReport($path))->finish(new SuiteResult([$file], 0.0));
    $xml = file_get_contents($path);
    unlink($path);
    equal(true, strpos($xml, 'name="bell"') !== false);
});

test('GitLab says when it cannot write the report', function () {
    $e = throws(RuntimeException::class, fn () => (new GitlabReport('/no/such/dir/junit.xml'))->finish(new SuiteResult([], 0.0)));
    equal(0, strpos($e->getMessage(), 'cannot write /no/such/dir/junit.xml: '));
});
