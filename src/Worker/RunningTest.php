<?php

declare(strict_types=1);

namespace Baretest\Worker;

use Baretest\Core\Failure;
use Baretest\Core\TestResult;

class RunningTest
{
    private string $name;
    private ?RunningTest $parent;
    private float $start;
    private ?Failure $failure = null;

    public function __construct(string $name, ?RunningTest $parent)
    {
        $this->name = $name;
        $this->parent = $parent;
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

    public function finish(): TestResult
    {
        return new TestResult($this->formatTitle(), microtime(true) - $this->start, $this->failure);
    }
}
