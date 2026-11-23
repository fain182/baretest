<?php

declare(strict_types=1);

namespace Baretest\Result;

/** What happened when one test file ran in its own process. */
class FileResult
{
    private string $file;
    /** @var list<TestResult> in the order they started, then any failure outside a test */
    private array $tests;
    private float $time;
    private string $output;

    public function __construct(string $file, array $tests, float $time, string $output)
    {
        $this->file = self::makeRelative($file);
        $this->tests = $tests;
        $this->time = $time;
        $this->output = $output;
    }

    public function getFile(): string
    {
        return $this->file;
    }

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

    public static function makeRelative(string $path): string
    {
        $prefix = getcwd() . DIRECTORY_SEPARATOR;
        return strncmp($path, $prefix, strlen($prefix)) === 0 ? substr($path, strlen($prefix)) : $path;
    }

    public function hasNoTests(): bool
    {
        return $this->tests === [];
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
        return count(array_filter($this->tests, static fn (TestResult $test): bool => $test->getSkipReason() !== null));
    }

    public function hasFailures(): bool
    {
        return $this->listFailures() !== [];
    }

    /** @return list<TestResult> the tests that failed themselves, not only through a subtest */
    public function listFailures(): array
    {
        return array_values(array_filter($this->tests, static fn (TestResult $test): bool => $test->getFailure() !== null));
    }
}
