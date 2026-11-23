<?php

declare(strict_types=1);

namespace Baretest\Core;

/** Why a test failed. */
class Failure
{
    private string $message;
    private ?string $label;
    private ?Location $location;
    private ?Comparison $comparison;

    public function __construct(string $message, ?string $label = null, ?Location $location = null, ?Comparison $comparison = null)
    {
        $this->message = $message;
        $this->label = $label;
        $this->location = $location;
        $this->comparison = $comparison;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function getComparison(): ?Comparison
    {
        return $this->comparison;
    }

    public function describe(): string
    {
        if ($this->label === null) {
            return $this->message;
        }
        return $this->message === '' ? $this->label : $this->label . ': ' . $this->message;
    }
}
