<?php

declare(strict_types=1);

namespace Baretest;

/** What happened when one test file ran in its own process. */
final class FileResult
{
    /**
     * @param list<array{path: list<string>, time: float, failure: ?array, subtestFailed: bool, skipped: ?string}> $tests in the order they started
     * @param ?array{title: string, failure: array} $stopped set when the file failed outside any test
     */
    public string $file;
    public array $tests;
    public ?array $stopped;
    public float $time;
    public string $output;

    public function __construct(string $file, array $tests, ?array $stopped, float $time, string $output)
    {
        $this->file = self::makeRelative($file);
        $this->tests = $tests;
        $this->stopped = $stopped;
        $this->time = $time;
        $this->output = $output;
    }

    public static function makeRelative(string $path): string
    {
        $prefix = getcwd() . DIRECTORY_SEPARATOR;
        return strncmp($path, $prefix, strlen($prefix)) === 0 ? substr($path, strlen($prefix)) : $path;
    }

    /** file:line of a failure, relative to the working directory. */
    public static function formatLocation(array $failure): ?string
    {
        if (!isset($failure['file'])) {
            return null;
        }
        return self::makeRelative($failure['file']) . (isset($failure['line']) ? ':' . $failure['line'] : '');
    }

    public static function describe(array $failure): string
    {
        $label = $failure['label'] ?? null;
        return $label === null ? $failure['message'] : ($failure['message'] === '' ? $label : $label . ': ' . $failure['message']);
    }

    /** @param array{path: list<string>} $test */
    public static function formatTitle(array $test): string
    {
        return implode(' › ', $test['path']);
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
        $failed = count(array_filter($this->tests, static fn (array $test): bool => $test['failure'] !== null || $test['subtestFailed']));
        return $failed + ($this->stopped !== null ? 1 : 0);
    }

    public function countSkipped(): int
    {
        return count(array_filter($this->tests, static fn (array $test): bool => $test['skipped'] !== null));
    }

    public function hasFailures(): bool
    {
        return $this->listFailures() !== [];
    }

    /** @return list<array{title: string, failure: array}> */
    public function listFailures(): array
    {
        $failures = [];
        foreach ($this->tests as $test) {
            if ($test['failure'] !== null) {
                $failures[] = ['title' => self::formatTitle($test), 'failure' => $test['failure']];
            }
        }
        return $this->stopped === null ? $failures : [...$failures, $this->stopped];
    }
}
