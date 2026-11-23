<?php

declare(strict_types=1);

namespace Baretest\Core;

/** What happened to one test. */
class TestResult
{
    private string $title;
    private float $time;
    private ?Failure $failure;

    public function __construct(string $title, float $time, ?Failure $failure)
    {
        $this->title = $title;
        $this->time = $time;
        $this->failure = $failure;
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

    public function hasFailed(): bool
    {
        return $this->failure !== null;
    }
}
