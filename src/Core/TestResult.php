<?php

declare(strict_types=1);

namespace Baretest\Core;

/** What happened to one test. */
class TestResult
{
    private string $title;
    private float $time;

    public function __construct(string $title, float $time)
    {
        $this->title = $title;
        $this->time = $time;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getTime(): float
    {
        return $this->time;
    }
}
