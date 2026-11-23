<?php

declare(strict_types=1);

namespace Baretest\Result;

/** What happened to one test, or to the code of its file outside any test. */
class TestResult
{
    private string $title;
    private float $time;
    private ?Failure $failure;
    private bool $subtestFailed;
    private ?string $skipped;

    public function __construct(string $title, float $time, ?Failure $failure, bool $subtestFailed, ?string $skipped)
    {
        $this->title = $title;
        $this->time = $time;
        $this->failure = $failure;
        $this->subtestFailed = $subtestFailed;
        $this->skipped = $skipped;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getTime(): float
    {
        return $this->time;
    }

    public function getFailure(): ?Failure
    {
        return $this->failure;
    }

    public function getSkipReason(): ?string
    {
        return $this->skipped;
    }

    /** @param list<string> $path the names of the test and of the tests around it, outermost first */
    public static function formatTitle(array $path): string
    {
        return implode(' › ', $path);
    }

    /** Why this test failed: its own failure, or one of its subtests. */
    public function findCause(): ?Failure
    {
        return $this->failure ?? ($this->subtestFailed ? new Failure('a subtest failed') : null);
    }

    public function hasFailed(): bool
    {
        return $this->failure !== null || $this->subtestFailed;
    }
}
