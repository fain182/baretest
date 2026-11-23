<?php

declare(strict_types=1);

namespace Baretest\Core;

/** What happened to one test. */
class TestResult
{
    private string $title;
    private float $time;
    private ?Failure $failure;
    private bool $subtestFailed;
    private ?string $skipReason;
    private bool $hasSubtests;

    public function __construct(string $title, float $time, ?Failure $failure, bool $subtestFailed = false, ?string $skipReason = null, bool $hasSubtests = false)
    {
        $this->title = $title;
        $this->time = $time;
        $this->failure = $failure;
        $this->subtestFailed = $subtestFailed;
        $this->skipReason = $skipReason;
        $this->hasSubtests = $hasSubtests;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getTime(): float
    {
        return $this->time;
    }

    /** The failure of this test itself, not of its subtests. */
    public function getFailure(): ?Failure
    {
        return $this->failure;
    }

    public function getSkipReason(): ?string
    {
        return $this->skipReason;
    }

    /** A test with subtests took their time too. */
    public function hasSubtests(): bool
    {
        return $this->hasSubtests;
    }

    public function isSkipped(): bool
    {
        return $this->skipReason !== null;
    }

    public function hasFailed(): bool
    {
        return $this->failure !== null || $this->subtestFailed;
    }

    /** Why this test failed: its own failure, or one of its subtests. */
    public function findCause(): ?Failure
    {
        return $this->failure ?? ($this->subtestFailed ? new Failure('a subtest failed') : null);
    }
}
