<?php

declare(strict_types=1);

namespace Baretest\Result;

/** Why a test failed: a message, where in the code, and for equal() the two values. */
class Failure
{
    public string $message;
    public ?string $label;
    public ?string $file;
    public ?int $line;
    public ?int $lastLine;
    public ?string $expected;
    public ?string $got;
    public ?string $expectedType;
    public ?string $gotType;
    public ?int $caret;

    /** @param array{message: string} $record as the worker wrote it */
    public function __construct(array $record)
    {
        $this->message = $record['message'];
        $this->label = $record['label'] ?? null;
        $this->file = $record['file'] ?? null;
        $this->line = $record['line'] ?? null;
        $this->lastLine = $record['lastLine'] ?? $this->line;
        $this->expected = $record['expected'] ?? null;
        $this->got = $record['got'] ?? null;
        $this->expectedType = $record['expectedType'] ?? null;
        $this->gotType = $record['gotType'] ?? null;
        $this->caret = $record['caret'] ?? null;
    }

    /** file:line, relative to the working directory. */
    public function formatLocation(): ?string
    {
        return $this->file === null ? null : FileResult::makeRelative($this->file) . ':' . $this->line;
    }

    public function describe(): string
    {
        if ($this->label === null) {
            return $this->message;
        }
        return $this->message === '' ? $this->label : $this->label . ': ' . $this->message;
    }
}
