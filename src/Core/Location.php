<?php

declare(strict_types=1);

namespace Baretest\Core;

/** The lines of a file where a failure happened. */
class Location
{
    private string $file;
    private int $line;
    private int $lastLine;

    public function __construct(string $file, int $line, int $lastLine)
    {
        $this->file = $file;
        $this->line = $line;
        $this->lastLine = $lastLine;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getLastLine(): int
    {
        return $this->lastLine;
    }

    public function format(): string
    {
        return $this->file . ':' . $this->line;
    }
}
