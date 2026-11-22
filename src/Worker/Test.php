<?php

declare(strict_types=1);

namespace Baretest\Worker;

/** One running test, in the process that runs its file. */
final class Test
{
    public string $name;
    private int $seq;
    private float $start;
    private ?array $failure = null;
    private bool $subtestFailed = false;
    private ?string $skipped = null;

    public function __construct(string $name, int $seq)
    {
        $this->name = $name;
        $this->seq = $seq;
        $this->start = microtime(true);
    }

    /** Keeps the first failure, the one that stopped the test even if the test caught it. */
    public function fail(array $failure): void
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

    /** @param list<string> $path the names of this test and of the tests around it */
    public function toRecord(array $path): array
    {
        return [
            't' => 'test',
            'seq' => $this->seq,
            'path' => $path,
            'time' => microtime(true) - $this->start,
            'failure' => $this->failure,
            'subtestFailed' => $this->subtestFailed,
            'skipped' => $this->hasFailed() ? null : $this->skipped,
        ];
    }
}
