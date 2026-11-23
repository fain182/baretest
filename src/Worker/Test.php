<?php

declare(strict_types=1);

namespace Baretest\Worker;

use Baretest\Result\Failure;

/** One running test, in the process that runs its file. */
class Test
{
    private string $name;
    private int $seq;
    private ?Test $parent;
    private float $start;
    private ?Failure $failure = null;
    private bool $subtestFailed = false;
    private ?string $skipped = null;

    public function __construct(string $name, int $seq, ?Test $parent)
    {
        $this->name = $name;
        $this->seq = $seq;
        $this->parent = $parent;
        $this->start = microtime(true);
    }

    /** The test this one runs inside, if any. */
    public function getParent(): ?Test
    {
        return $this->parent;
    }

    /** @return list<string> the names of this test and of the tests around it, outermost first */
    public function listPath(): array
    {
        return $this->parent === null ? [$this->name] : [...$this->parent->listPath(), $this->name];
    }

    /** Keeps the first failure, the one that stopped the test even if the test caught it. */
    public function fail(Failure $failure): void
    {
        $this->failure ??= $failure;
    }

    public function skip(string $reason): void
    {
        $this->skipped = $reason;
    }

    public function markSubtestFailed(): void
    {
        $this->subtestFailed = true;
    }

    public function hasFailed(): bool
    {
        return $this->failure !== null || $this->subtestFailed;
    }

    public function buildRecord(): array
    {
        return [
            't' => 'test',
            'seq' => $this->seq,
            'path' => $this->listPath(),
            'time' => microtime(true) - $this->start,
            'failure' => $this->failure,
            'subtestFailed' => $this->subtestFailed,
            'skipped' => $this->hasFailed() ? null : $this->skipped,
        ];
    }
}
