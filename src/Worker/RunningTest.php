<?php

declare(strict_types=1);

namespace Baretest\Worker;

use Baretest\Core\TestResult;

class RunningTest
{
    private string $name;
    private ?RunningTest $parent;
    private float $start;

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

    public function finish(): TestResult
    {
        return new TestResult($this->formatTitle(), microtime(true) - $this->start);
    }
}
