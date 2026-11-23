<?php

declare(strict_types=1);

namespace Baretest\Core;

/** A test that stood out as slow in its run. */
class SlowTest
{
    private TestFile $file;
    private TestResult $test;

    public function __construct(TestFile $file, TestResult $test)
    {
        $this->file = $file;
        $this->test = $test;
    }

    public function getFile(): TestFile
    {
        return $this->file;
    }

    public function getTest(): TestResult
    {
        return $this->test;
    }
}
