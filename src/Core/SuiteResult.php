<?php

declare(strict_types=1);

namespace Baretest\Core;

/** What happened to every test file of a run, in the order they ran. */
class SuiteResult
{
    /** @var list<FileResult> */
    private array $files;
    private float $time;

    /** @param list<FileResult> $files */
    public function __construct(array $files, float $time)
    {
        $this->files = $files;
        $this->time = $time;
    }

    /** @return list<FileResult> */
    public function listFiles(): array
    {
        return $this->files;
    }

    public function getTime(): float
    {
        return $this->time;
    }

    public function countFiles(): int
    {
        return count($this->files);
    }

    public function countTests(): int
    {
        return array_sum(array_map(static fn (FileResult $file): int => $file->countTests(), $this->files));
    }

    public function countFailed(): int
    {
        return array_sum(array_map(static fn (FileResult $file): int => $file->countFailed(), $this->files));
    }

    public function countFailedFiles(): int
    {
        return count(array_filter($this->files, static fn (FileResult $file): bool => $file->hasFailures()));
    }

    public function computeExitCode(): int
    {
        return $this->countFailedFiles() > 0 ? 1 : 0;
    }
}
