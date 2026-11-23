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
    public ?Comparison $comparison;

    /** @param array{message: string} $record as the worker wrote it */
    public function __construct(array $record)
    {
        $this->message = $record['message'];
        $this->label = $record['label'] ?? null;
        $this->file = $record['file'] ?? null;
        $this->line = $record['line'] ?? null;
        $this->lastLine = $record['lastLine'] ?? $this->line;
        $this->comparison = isset($record['expected'])
            ? new Comparison($record['expected'], $record['got'], $record['expectedType'], $record['gotType'], $record['caret'])
            : null;
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
