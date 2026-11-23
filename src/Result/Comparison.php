<?php

declare(strict_types=1);

namespace Baretest\Result;

/** The two values of a failed equal(), as rendered in the worker. */
class Comparison
{
    private string $expected;
    private string $got;
    private string $expectedType;
    private string $gotType;
    /** The column of the first difference, or null unless both values are strings or both arrays. */
    private ?int $caret;

    public function __construct(string $expected, string $got, string $expectedType, string $gotType, ?int $caret)
    {
        $this->expected = $expected;
        $this->got = $got;
        $this->expectedType = $expectedType;
        $this->gotType = $gotType;
        $this->caret = $caret;
    }

    public function getExpected(): string
    {
        return $this->expected;
    }

    public function getGot(): string
    {
        return $this->got;
    }

    public function getExpectedType(): string
    {
        return $this->expectedType;
    }

    public function getGotType(): string
    {
        return $this->gotType;
    }

    public function getCaret(): ?int
    {
        return $this->caret;
    }

    public function describe(): string
    {
        return sprintf('expected %s (%s), got %s (%s)', $this->expected, $this->expectedType, $this->got, $this->gotType);
    }
}
