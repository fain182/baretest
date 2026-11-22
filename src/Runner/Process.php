<?php

declare(strict_types=1);

namespace Baretest\Runner;

use Baretest\Result\FileResult;
use Baretest\Result\Records;

/** Runs a test file in a fresh PHP process and reads back what it recorded. */
final class Process
{
    /** @var ?list<string> */
    private static ?array $command = null;

    public static function run(string $file): FileResult
    {
        $records = tempnam(sys_get_temp_dir(), 'baretest');
        $output = tempnam(sys_get_temp_dir(), 'baretest');
        $start = microtime(true);
        $process = proc_open(
            [...self::buildCommand(), dirname(__DIR__) . '/Worker/worker.php', $file, $records, self::findAutoload()],
            [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['file', $output, 'w'], 2 => ['redirect', 1]],
            $pipes,
        );
        if ($process === false) {
            unlink($records);
            unlink($output);
            throw new \RuntimeException('could not start ' . PHP_BINARY);
        }
        $code = proc_close($process);
        $time = microtime(true) - $start;

        $tests = [];
        $stopped = null;
        $done = false;
        foreach (Records::read($records) as $record) {
            if ($record['t'] === 'test') {
                $tests[] = $record;
            } elseif ($record['t'] === 'file') {
                $stopped = self::buildFailedTest($record['title'], $record['failure']);
            } elseif ($record['t'] === 'done') {
                $done = true;
            }
        }
        usort($tests, static fn (array $a, array $b): int => $a['seq'] <=> $b['seq']);
        $printed = (string) file_get_contents($output);
        if ((!$done || $code !== 0) && $stopped === null) {
            $stopped = self::buildFailedTest('crashed', ['message' => self::describeCrash($code, $printed)]);
            $printed = '';
        }
        unlink($records);
        unlink($output);

        return new FileResult($file, $stopped === null ? $tests : [...$tests, $stopped], $time, $printed);
    }

    /** A failure outside any test, listed after the tests as one more failed test. */
    private static function buildFailedTest(string $title, array $failure): array
    {
        return ['path' => [$title], 'time' => 0.0, 'failure' => $failure, 'subtestFailed' => false, 'skipped' => null];
    }

    /**
     * PHP with the settings of this process: the same php.ini, or none, and a -d only for the
     * settings that differ from it, since some values (`a=href,form=`) do not survive the ini parser.
     */
    public static function buildCommand(): array
    {
        if (self::$command !== null) {
            return self::$command;
        }
        $ini = php_ini_loaded_file();
        $command = $ini === false ? [PHP_BINARY, '-n'] : [PHP_BINARY, '-c', $ini];
        $probe = proc_open([...$command, '-r', 'echo serialize(ini_get_all(null, false));'], [1 => ['pipe', 'w']], $pipes);
        if ($probe === false) {
            throw new \RuntimeException('could not start ' . PHP_BINARY);
        }
        $defaults = unserialize(stream_get_contents($pipes[1]), ['allowed_classes' => false]) ?: [];
        proc_close($probe);
        foreach (ini_get_all(null, false) as $name => $value) {
            if ($value !== null && ($defaults[$name] ?? null) !== $value) {
                array_push($command, '-d', "$name=$value");
            }
        }
        return self::$command = $command;
    }

    private static function findAutoload(): string
    {
        return dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2) . '/autoload.php';
    }

    private static function describeCrash(int $code, string $output): string
    {
        $message = "the process ended unexpectedly (exit code $code)";
        $lines = array_slice(explode("\n", trim($output)), -10);
        return $output === '' ? $message : $message . "\n" . implode("\n", $lines);
    }
}
