<?php

declare(strict_types=1);

namespace Baretest\Core;

/** A test that stood out as slow in its run. */
class SlowTest
{
    private TestFile $file;
    private TestResult $test;
    private int $timesMedian;

    public function __construct(TestFile $file, TestResult $test, int $timesMedian)
    {
        $this->file = $file;
        $this->test = $test;
        $this->timesMedian = $timesMedian;
    }

    public function getFile(): TestFile
    {
        return $this->file;
    }

    public function getTest(): TestResult
    {
        return $this->test;
    }

    /** How many times the median test of the run it took. */
    public function getTimesMedian(): int
    {
        return $this->timesMedian;
    }
}
