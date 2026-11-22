<?php

declare(strict_types=1);

namespace Baretest\Result;

/** What happened to one test, or to the code of its file outside any test. */
final class TestResult
{
    public string $title;
    public float $time;
    public ?Failure $failure;
    public bool $subtestFailed;
    public ?string $skipped;

    public function __construct(string $title, float $time, ?Failure $failure, bool $subtestFailed, ?string $skipped)
    {
        $this->title = $title;
        $this->time = $time;
        $this->failure = $failure;
        $this->subtestFailed = $subtestFailed;
        $this->skipped = $skipped;
    }

    /** @param list<string> $path the names of the test and of the tests around it, outermost first */
    public static function formatTitle(array $path): string
    {
        return implode(' › ', $path);
    }

    public function hasFailed(): bool
    {
        return $this->failure !== null || $this->subtestFailed;
    }
}
