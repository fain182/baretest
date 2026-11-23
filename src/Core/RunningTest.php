<?php

declare(strict_types=1);

namespace Baretest\Core;

/** A test while it runs: the rules for how its failures, skips and subtests decide its result. */
class RunningTest
{
    private string $name;
    private ?RunningTest $parent;
    private float $start;
    private ?Failure $failure = null;
    private bool $subtestFailed = false;
    private bool $hasSubtests = false;
    private ?string $skipReason = null;

    public function __construct(string $name, ?RunningTest $parent)
    {
        $this->name = $name;
        $this->parent = $parent;
        if ($parent !== null) {
            $parent->hasSubtests = true;
        }
        $this->start = microtime(true);
    }

    public function getParent(): ?RunningTest
    {
        return $this->parent;
    }

    /** The names of this test and of the tests around it, outermost first. */
    public function formatTitle(): string
    {
        return $this->parent === null ? $this->name : $this->parent->formatTitle() . ' › ' . $this->name;
    }

    /** Keeps the first failure, the one that stopped the test even if the test caught it. */
    public function fail(Failure $failure): void
    {
        $this->failure ??= $failure;
    }

    private function markSubtestFailed(): void
    {
        $this->subtestFailed = true;
    }

    public function skip(string $reason): void
    {
        $this->skipReason = $reason;
    }

    /** A test that failed stays failed even if it was then skipped, and fails the test around it. */
    public function finish(): TestResult
    {
        $failed = $this->failure !== null || $this->subtestFailed;
        if ($failed && $this->parent !== null) {
            $this->parent->markSubtestFailed();
        }
        return new TestResult($this->formatTitle(), microtime(true) - $this->start, $this->failure, $this->subtestFailed, $failed ? null : $this->skipReason, $this->hasSubtests);
    }
}
