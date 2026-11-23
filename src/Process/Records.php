<?php

declare(strict_types=1);

namespace Baretest\Process;

use Baretest\Core\Comparison;
use Baretest\Core\Failure;
use Baretest\Core\Location;
use Baretest\Core\TestResult;

/**
 * The file through which a worker tells the runner what happened, one line per finished
 * test, so that a crash loses only the test it hit.
 */
class Records
{
    private const CLOSED = 'closed';

    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function add(int $order, TestResult $test): void
    {
        $this->append(base64_encode(serialize([$order, $test])));
    }

    /** Records a failure outside any test; it is listed after the tests. */
    public function failFile(TestResult $test): void
    {
        $this->add(PHP_INT_MAX, $test);
    }

    public function hasFileFailure(): bool
    {
        return isset($this->readRecords()[PHP_INT_MAX]);
    }

    /** Marks the file as run to its end. */
    public function close(): void
    {
        $this->append(self::CLOSED);
    }

    public function isClosed(): bool
    {
        return in_array(self::CLOSED, $this->readLines(), true);
    }

    /** @return list<TestResult> in the order they started */
    public function readTests(): array
    {
        return array_values($this->readRecords());
    }

    /** @return array<int, TestResult> by order; a line cut short by a crash is left out */
    private function readRecords(): array
    {
        $tests = [];
        foreach ($this->readLines() as $line) {
            $record = @unserialize((string) base64_decode($line, true), ['allowed_classes' => [TestResult::class, Failure::class, Location::class, Comparison::class]]);
            if (is_array($record)) {
                $tests[$record[0]] = $record[1];
            }
        }
        ksort($tests);
        return $tests;
    }

    private function append(string $line): void
    {
        file_put_contents($this->path, $line . "\n", FILE_APPEND);
    }

    /** @return list<string> */
    private function readLines(): array
    {
        return file($this->path, FILE_IGNORE_NEW_LINES) ?: [];
    }
}
