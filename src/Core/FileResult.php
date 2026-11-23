<?php

declare(strict_types=1);

namespace Baretest\Core;

/** What happened when one test file ran. */
class FileResult
{
    private string $file;
    /** @var list<TestResult> */
    private array $tests;
    private float $time;
    private string $output;

    /** @param list<TestResult> $tests in the order they started */
    public function __construct(string $file, array $tests, float $time, string $output)
    {
        $this->file = $file;
        $this->tests = $tests;
        $this->time = $time;
        $this->output = $output;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    /** @return list<TestResult> */
    public function listTests(): array
    {
        return $this->tests;
    }

    public function getTime(): float
    {
        return $this->time;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function countTests(): int
    {
        return count($this->tests);
    }

    public function countFailed(): int
    {
        return count(array_filter($this->tests, static fn (TestResult $test): bool => $test->hasFailed()));
    }

    public function countSkipped(): int
    {
        return count(array_filter($this->tests, static fn (TestResult $test): bool => $test->isSkipped()));
    }

    public function hasFailures(): bool
    {
        return $this->countFailed() > 0;
    }

    public function hasNoTests(): bool
    {
        return $this->tests === [];
    }

    /** @return list<TestResult> the tests that failed themselves, not only through a subtest */
    public function listFailures(): array
    {
        return array_values(array_filter($this->tests, static fn (TestResult $test): bool => $test->getFailure() !== null));
    }
}
