<?php

declare(strict_types=1);

namespace Baretest\Core;

/** What happened when one test file ran. */
class FileResult
{
    private const CRASH_LINES = 10;

    private TestFile $file;
    /** @var list<TestResult> */
    private array $tests;
    private float $time;
    private string $output;

    /** @param list<TestResult> $tests in the order they started */
    public function __construct(TestFile $file, array $tests, float $time, string $output)
    {
        $this->file = $file;
        $this->tests = $tests;
        $this->time = $time;
        $this->output = $output;
    }

    public function getFile(): TestFile
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

    /** This file once its process ended without saying why: the crash, with the last lines it printed, is listed after the tests. */
    public function crash(int $exitCode): self
    {
        $message = "the process ended unexpectedly (exit code $exitCode)";
        $printed = trim($this->output);
        if ($printed !== '') {
            $message .= "\n" . implode("\n", array_slice(explode("\n", $printed), -self::CRASH_LINES));
        }
        return new self($this->file, [...$this->tests, new TestResult('crashed', 0.0, new Failure($message))], $this->time, '');
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
