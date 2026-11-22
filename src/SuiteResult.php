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

    public function hasFailures(): bool
    {
        foreach ($this->files as $file) {
            if ($file->hasFailures()) {
                return true;
            }
        }
        return false;
    }

    public function hasFileWithoutTests(): bool
    {
        foreach ($this->files as $file) {
            if ($file->hasNoTests()) {
                return true;
            }
        }
        return false;
    }

    /** @return \ArrayIterator<int, FileResult> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->files);
    }
}
