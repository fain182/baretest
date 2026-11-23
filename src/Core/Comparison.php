<?php

declare(strict_types=1);

namespace Baretest\Core;

/** The two values of a failed equal(), as the worker rendered them. */
class Comparison
{
    private string $expected;
    private string $got;
    private string $expectedType;
    private string $gotType;
    private ?int $caret;

    /** @param ?int $caret the byte of $got where it first differs from $expected, if the two can be compared that way */
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
