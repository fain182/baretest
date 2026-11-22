<?php

declare(strict_types=1);

namespace Baretest;

/** What happened when one test file ran in its own process. */
final class FileResult
{
    /**
     * @param list<array{path: list<string>, time: float, failure: ?array, subtestFailed: bool}> $tests in the order they started
     * @param ?array{title: string, failure: array} $stopped set when the file failed outside any test
     */
    public function __construct(
        public readonly string $file,
        public readonly array $tests,
        public readonly ?array $stopped,
        public readonly float $time,
    ) {
    }

    /** @param array{path: list<string>} $test */
    public static function title(array $test): string
    {
        return implode(' › ', $test['path']);
    }

    /** @param array{failure: ?array, subtestFailed: bool} $test */
    public static function hasFailed(array $test): bool
    {
        return $test['failure'] !== null || $test['subtestFailed'];
    }

    public function hasNoTests(): bool
    {
        return $this->tests === [] && $this->stopped === null;
    }

    public function countTests(): int
    {
        return count($this->tests) + ($this->stopped !== null ? 1 : 0);
    }

    public function countFailed(): int
    {
        $failed = count(array_filter($this->tests, self::hasFailed(...)));
        return $failed + ($this->stopped !== null ? 1 : 0);
    }

    /** @return list<array{title: string, failure: array}> */
    public function failures(): array
    {
        $failures = [];
        foreach ($this->tests as $test) {
            if ($test['failure'] !== null) {
                $failures[] = ['title' => self::title($test), 'failure' => $test['failure']];
            }
        }
        return $this->stopped === null ? $failures : [...$failures, $this->stopped];
    }
}
