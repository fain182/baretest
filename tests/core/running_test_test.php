<?php

use Baretest\Core\Failure;
use Baretest\Core\RunningTest;

use function Baretest\{test, equal};

test('a running test', function () {
    test('finishes with its name after the names of the tests around it', function () {
        $child = new RunningTest('child', new RunningTest('parent', null));
        equal('parent › child', $child->finish()->getTitle());
    });
    test('passes when nothing failed', fn () => equal(false, (new RunningTest('a', null))->finish()->hasFailed()));
    test('keeps its first failure, even if the test caught it', function () {
        $test = new RunningTest('a', null);
        $test->fail(new Failure('first'));
        $test->fail(new Failure('second'));
        equal('first', $test->finish()->getFailure()->describe());
    });
    test('fails the test around it when it fails', function () {
        $parent = new RunningTest('parent', null);
        $child = new RunningTest('child', $parent);
        $child->fail(new Failure('no'));
        $child->finish();
        $result = $parent->finish();
        equal([true, null], [$result->hasFailed(), $result->getFailure()]);
    });
    test('fails the tests further out too', function () {
        $outer = new RunningTest('outer', null);
        $middle = new RunningTest('middle', $outer);
        $inner = new RunningTest('inner', $middle);
        $inner->fail(new Failure('no'));
        $inner->finish();
        $middle->finish();
        equal(true, $outer->finish()->hasFailed());
    });
    test('is skipped with its reason', function () {
        $test = new RunningTest('a', null);
        $test->skip('needs mbstring');
        equal('needs mbstring', $test->finish()->getSkipReason());
    });
    test('stays failed when it is skipped after a subtest failed', function () {
        $parent = new RunningTest('parent', null);
        $child = new RunningTest('child', $parent);
        $child->fail(new Failure('no'));
        $child->finish();
        $parent->skip('too late');
        $result = $parent->finish();
        equal([true, false], [$result->hasFailed(), $result->isSkipped()]);
    });
});
