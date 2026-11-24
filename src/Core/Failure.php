<?php

declare(strict_types=1);

namespace Baretest\Core;

/** Why a test failed. */
class Failure
{
    private string $message;
    private ?string $label;
    private ?Location $location;
    /** @var list<Comparison> */
    private array $comparisons;

    /** @param list<Comparison> $comparisons */
    public function __construct(string $message, ?string $label = null, ?Location $location = null, array $comparisons = [])
    {
        $this->message = $message;
        $this->label = $label;
        $this->location = $location;
        $this->comparisons = $comparisons;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    /** @return list<Comparison> */
    public function listComparisons(): array
    {
        return $this->comparisons;
    }

    public function describe(): string
    {
        if ($this->label === null) {
            return $this->message;
        }
        return $this->message === '' ? $this->label : $this->label . ': ' . $this->message;
    }
}
