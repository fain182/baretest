<?php

declare(strict_types=1);

namespace Baretest\Core;

/** What happened to every test file of a run, in the order they ran. */
class SuiteResult
{
    private const SLOW_SECONDS = 0.5;
    private const SLOW_TIMES_MEDIAN = 10;
    private const SLOW_SHOWN = 5;

    /** @var list<FileResult> */
    private array $files;
    private float $time;

    /** @param list<FileResult> $files */
    public function __construct(array $files, float $time)
    {
        $this->files = $files;
        $this->time = $time;
    }

    /** @return list<FileResult> */
    public function listFiles(): array
    {
        return $this->files;
    }

    public function getTime(): float
    {
        return $this->time;
    }

    public function countFiles(): int
    {
        return count($this->files);
    }

    public function countTests(): int
    {
        return array_sum(array_map(static fn (FileResult $file): int => $file->countTests(), $this->files));
    }

    public function countFailed(): int
    {
        return array_sum(array_map(static fn (FileResult $file): int => $file->countFailed(), $this->files));
    }

    public function countSkipped(): int
    {
        return array_sum(array_map(static fn (FileResult $file): int => $file->countSkipped(), $this->files));
    }

    public function countFailedFiles(): int
    {
        return count(array_filter($this->files, static fn (FileResult $file): bool => $file->hasFailures()));
    }

    public function countEmptyFiles(): int
    {
        return count(array_filter($this->files, static fn (FileResult $file): bool => $file->hasNoTests()));
    }

    /**
     * The tests that took at least half a second and ten times the median test, the slowest
     * first. Only tests without subtests count, and not what did not run as a test, like a crash.
     *
     * @return list<SlowTest>
     */
    public function listSlowTests(): array
    {
        $tests = [];
        foreach ($this->files as $file) {
            foreach ($file->listTests() as $test) {
                if (!$test->hasSubtests() && $test->getTime() > 0.0) {
                    $tests[] = [$file->getFile(), $test];
                }
            }
        }
        if ($tests === []) {
            return [];
        }
        $times = array_map(static fn (array $entry): float => $entry[1]->getTime(), $tests);
        sort($times);
        $middle = intdiv(count($times), 2);
        $median = count($times) % 2 === 1 ? $times[$middle] : ($times[$middle - 1] + $times[$middle]) / 2;
        $slow = array_filter($tests, static fn (array $entry): bool => $entry[1]->getTime() >= max(self::SLOW_SECONDS, self::SLOW_TIMES_MEDIAN * $median));
        usort($slow, static fn (array $a, array $b): int => $b[1]->getTime() <=> $a[1]->getTime());
        return array_map(
            static fn (array $entry): SlowTest => new SlowTest($entry[0], $entry[1], (int) round($entry[1]->getTime() / $median)),
            array_slice($slow, 0, self::SLOW_SHOWN),
        );
    }

    /** 2 when a file ran no tests, 1 when a test failed, 0 when every test passed. */
    public function computeExitCode(): int
    {
        if ($this->countEmptyFiles() > 0) {
            return 2;
        }
        return $this->countFailedFiles() > 0 ? 1 : 0;
    }
}
