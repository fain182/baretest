<?php

declare(strict_types=1);

namespace Baretest;

/** Runs a test file in a fresh PHP process and reads back what it recorded. */
final class Process
{
    public static function run(string $file, string $display): FileResult
    {
        $records = tempnam(sys_get_temp_dir(), 'baretest');
        $output = tempnam(sys_get_temp_dir(), 'baretest');
        $start = microtime(true);
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/worker.php', $file, $records],
            [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['file', $output, 'w'], 2 => ['redirect', 1]],
            $pipes,
        );
        if ($process === false) {
            throw new \RuntimeException('could not start ' . PHP_BINARY);
        }
        $code = proc_close($process);
        $time = microtime(true) - $start;

        $tests = [];
        $stopped = null;
        $done = false;
        foreach (file($records, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $record = json_decode($line, true);
            match ($record['t'] ?? null) {
                'test' => $tests[] = $record,
                'file' => $stopped = $record,
                'done' => $done = true,
                default => null,
            };
        }
        usort($tests, static fn (array $a, array $b): int => $a['seq'] <=> $b['seq']);
        if (!$done && $stopped === null) {
            $stopped = ['title' => 'crashed', 'failure' => ['message' => self::describeCrash($code, (string) file_get_contents($output))]];
        }
        unlink($records);
        unlink($output);

        return new FileResult($display, $tests, $stopped, $time);
    }

    private static function describeCrash(int $code, string $output): string
    {
        $message = "the process ended unexpectedly (exit code $code)";
        $lines = array_slice(explode("\n", trim($output)), -10);
        return $output === '' ? $message : $message . "\n" . implode("\n", $lines);
    }
}
