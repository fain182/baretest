<?php

declare(strict_types=1);

namespace Baretest\Result;

/** Why a test failed: a message, where in the code, and for equal() the two values. */
class Failure
{
    private string $message;
    private ?string $label;
    private ?string $file;
    private ?int $line;
    private ?int $lastLine;
    private ?Comparison $comparison;

    public function __construct(string $message, ?string $label = null, ?string $file = null, ?int $line = null, ?int $lastLine = null, ?Comparison $comparison = null)
    {
        $this->message = $message;
        $this->label = $label;
        $this->file = $file;
        $this->line = $line;
        $this->lastLine = $lastLine ?? $line;
        $this->comparison = $comparison;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function getLine(): ?int
    {
        return $this->line;
    }

    public function getLastLine(): ?int
    {
        return $this->lastLine;
    }

    public function getComparison(): ?Comparison
    {
        return $this->comparison;
    }

    /** file:line, relative to the working directory. */
    public function formatLocation(): ?string
    {
        return $this->file === null ? null : FileResult::makeRelative($this->file) . ':' . $this->line;
    }

    /** The file that failed, relative to the working directory; $testFile when the failure has no location. */
    public function locateFile(string $testFile): string
    {
        return $this->file === null ? $testFile : FileResult::makeRelative($this->file);
    }

    public function describe(): string
    {
        if ($this->label === null) {
            return $this->message;
        }
        return $this->message === '' ? $this->label : $this->label . ': ' . $this->message;
    }
}
