<?php

declare(strict_types=1);

namespace Baretest;

/** What happened to every test file of a run, in the order they ran. */
final class SuiteResult implements \IteratorAggregate
{
    /** @var list<FileResult> */
    private array $files = [];

    public function append(FileResult $file): void
    {
        $this->files[] = $file;
    }

    public function getLast(): FileResult
    {
        return $this->files[array_key_last($this->files)];
    }

    /** @param ?callable(FileResult): bool $filter */
    public function countFiles(?callable $filter = null): int
    {
        return count($filter === null ? $this->files : array_filter($this->files, $filter));
    }

    /** @param callable(FileResult): int $count */
    public function sum(callable $count): int
    {
        return array_sum(array_map($count, $this->files));
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->files);
    }
}
